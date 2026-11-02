<?php

namespace App\Http\Controllers;

use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Enums\RolloutStage;
use App\Enums\TriageCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Ward;
use App\Services\WardConfigRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WardController extends Controller
{
    public function __construct(private WardConfigRepository $configs) {}

    public function index()
    {
        $wards = Ward::where('is_active', true)->with('config')->withCount('activeEncounters')->orderBy('name')->get()
            ->filter(fn (Ward $w) => $w->config?->rollout_stage !== RolloutStage::NotStarted || auth()->user()->isAdmin());

        return view('wards.index', ['wards' => $wards->groupBy(fn ($w) => $w->type()?->label() ?? 'Unconfigured')]);
    }

    /** Ward selection: remember the ward and warm its config cache. */
    public function select(Request $request, Ward $ward)
    {
        $request->session()->put('ward_id', $ward->id);
        $this->configs->for($ward);

        return redirect()->route('wards.show', $ward);
    }

    /**
     * The ward screen shell. It renders straight from the cached config; the
     * patient list is fetched separately so the screen appears first.
     */
    public function show(Request $request, Ward $ward)
    {
        $request->session()->put('ward_id', $ward->id);
        $config = $this->configs->for($ward);

        return view('wards.show', [
            'ward' => $ward,
            'config' => $config,
            'openTasks' => $ward->tasks()->whereNull('done_at')
                ->where('for_role', $request->user()->role->value)->count(),
        ]);
    }

    /** Patient list fragment, loaded asynchronously by the ward screen. */
    public function patients(Request $request, Ward $ward)
    {
        $config = $this->configs->for($ward);
        $user = $request->user();

        $encounters = $ward->activeEncounters()
            ->with(['patient', 'bed', 'team', 'assignedNurse', 'latestObservation', 'pendingTransfer.toWard'])
            ->withCount([
                'entries as open_alerts_count' => fn ($q) => $q->where('category', EntryCategory::Alert->value)->where('status', 'open'),
                'entries as pending_samples_count' => fn ($q) => $q->where('category', EntryCategory::Investigation->value)->where('type', 'lab')->where('status', 'requested'),
            ])
            ->get();

        $incoming = Transfer::where('to_ward_id', $ward->id)->where('status', Transfer::PENDING)
            ->with(['encounter.patient', 'fromWard', 'toTeam', 'requester'])->get();

        $view = match ($config->ward_type) {
            WardType::Emergency => 'wards.partials.emergency-board',
            WardType::CriticalCare => 'wards.partials.critical-care-board',
            default => 'wards.partials.general-board',
        };

        $data = compact('ward', 'config', 'encounters', 'incoming');

        if ($config->ward_type === WardType::Emergency) {
            // Untriaged first, then by triage category, then longest waiting.
            $data['encounters'] = $encounters->sortBy([
                fn ($a, $b) => ($a->triage_category?->value ?? 0) <=> ($b->triage_category?->value ?? 0),
                fn ($a, $b) => $a->arrived_at <=> $b->arrived_at,
            ])->values();
            $data['stats'] = [
                'waiting' => $encounters->whereNull('seen_at')->count(),
                'breaching' => $encounters->filter->isBreachingTarget()->count(),
                'longest' => $encounters->whereNull('seen_at')->max(fn ($e) => $e->waitingMinutes()) ?? 0,
                'awaiting_bed' => $encounters->filter(fn ($e) => $e->pendingTransfer)->count(),
            ];
        } else {
            $data['beds'] = $ward->beds()->where('is_active', true)->get();
            $data['byBed'] = $encounters->keyBy('bed_id');
            $data['unbedded'] = $encounters->whereNull('bed_id');
        }

        if ($config->ward_type === WardType::CriticalCare) {
            $data['nurses'] = User::where('role', UserRole::Nurse)->where('ward_id', $ward->id)->orderBy('name')->get();
            $data['mineOnly'] = $request->boolean('mine') && $user->isNurse();
        }

        return view($view, $data);
    }

    /**
     * Phase 3: the config the client caches. Clients send the version they hold
     * in If-None-Match and get 304 until an admin changes the config.
     */
    public function config(Request $request, Ward $ward)
    {
        $config = $this->configs->for($ward);
        $etag = '"ward-'.$ward->id.'-v'.$config->version.'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->setEtag(trim($etag, '"'));
        }

        return response()->json($this->configs->clientPayload($ward, $config))
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'private, no-cache');
    }

    /** Critical care 1:1 view: assign the bedside nurse. */
    public function assignNurse(Request $request, Ward $ward, Encounter $encounter)
    {
        abort_unless($encounter->ward_id === $ward->id && $ward->type() === WardType::CriticalCare, 404);
        $data = $request->validate(['nurse_id' => ['nullable', 'exists:users,id']]);
        $encounter->update(['assigned_nurse_id' => $data['nurse_id'] ?: null]);

        return back()->with('status', 'Bedside nurse updated.');
    }

    /** A&E: register a new arrival onto the board. */
    public function registerArrival(Request $request, Ward $ward)
    {
        abort_unless($ward->type() === WardType::Emergency, 404);
        $data = $request->validate([
            'mrn' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', 'in:female,male,other'],
            'presenting_complaint' => ['required', 'string', 'max:255'],
            'triage_category' => ['nullable', Rule::enum(TriageCategory::class)],
        ]);

        $patient = Patient::firstOrCreate(
            ['mrn' => ($data['mrn'] ?? null) ?: 'ED'.now()->format('ymdHis').random_int(10, 99)],
            ['name' => $data['name'], 'date_of_birth' => $data['date_of_birth'], 'sex' => $data['sex']],
        );

        if ($patient->encounters()->active()->exists()) {
            return back()->withErrors(['mrn' => 'This patient already has an open attendance or admission.']);
        }

        $patient->encounters()->create([
            'ward_id' => $ward->id,
            'status' => EncounterStatus::Waiting,
            'presenting_complaint' => $data['presenting_complaint'],
            'arrived_at' => now(),
            'triage_category' => $data['triage_category'] ?? null,
            'triaged_at' => isset($data['triage_category']) ? now() : null,
        ]);

        return back()->with('status', "{$patient->name} added to the board.");
    }
}
