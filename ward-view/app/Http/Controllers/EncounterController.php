<?php

namespace App\Http\Controllers;

use App\Enums\DnrStatus;
use App\Enums\EntryCategory;
use App\Models\ClinicalEntry;
use App\Models\Encounter;
use App\Models\Transfer;
use App\Services\PatientMovement;
use App\Services\WardConfigRepository;
use App\Ward\Actions\ActionRegistry;
use App\Ward\PatientHeader;
use App\Ward\Tabs\TabRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EncounterController extends Controller
{
    public function __construct(
        private WardConfigRepository $configs,
        private ActionRegistry $actions,
        private TabRegistry $tabs,
    ) {}

    /**
     * The patient view: header badges and the role's button bar render
     * immediately; each tab's content loads when it is opened.
     */
    public function show(Request $request, Encounter $encounter)
    {
        $encounter->load(['patient', 'ward', 'bed', 'team', 'latestObservation', 'pendingTransfer.toWard', 'assignedNurse']);
        $user = $request->user();

        // A nurse accepting a transfer works from the receiving ward's config.
        $wardId = $encounter->pendingTransfer && session('ward_id') === $encounter->pendingTransfer->to_ward_id
            ? $encounter->pendingTransfer->to_ward_id
            : $encounter->ward_id;
        $config = $this->configs->for($encounter->ward_id);
        $buttons = $user->isClinical() ? $this->actions->enabledFor($config, $user->role) : collect();

        // The receiving nurse always gets Move Patient so they can accept.
        if ($wardId !== $encounter->ward_id && $user->isNurse()) {
            $buttons = collect(['move_patient' => $this->actions->find('move_patient')]);
        }

        $tabs = $this->tabs->enabledFor($config);
        $active = $request->query('tab', $tabs->keys()->first());

        return view('encounters.show', [
            'encounter' => $encounter,
            'config' => $config,
            'header' => new PatientHeader($encounter, $config),
            'buttons' => $buttons,
            'tabs' => $tabs,
            'activeTab' => $tabs->has($active) ? $active : $tabs->keys()->first(),
            'openAction' => session('open_action'),
            'riskDefs' => config('ward.risk_assessments'),
        ]);
    }

    /** Tab content fragment. */
    public function tab(Encounter $encounter, string $key)
    {
        $config = $this->configs->for($encounter->ward_id);
        abort_unless($this->tabs->enabledFor($config)->has($key), 404);
        $tab = $this->tabs->find($key);

        return view($tab->view(), ['encounter' => $encounter, 'config' => $config, 'tab' => $tab] + $tab->data($encounter, $config));
    }

    /** Header badges: tap to edit. Doctors own resus status and the critical flag. */
    public function updateHeader(Request $request, Encounter $encounter, string $field)
    {
        $user = $request->user();
        $patient = $encounter->patient;

        switch ($field) {
            case 'resus':
                abort_unless($user->isDoctor(), 403);
                $data = $request->validate(['dnr_status' => ['required', Rule::enum(DnrStatus::class)], 'reason' => ['nullable', 'string', 'max:500']]);
                $patient->update(['dnr_status' => $data['dnr_status']]);
                $summary = 'Resuscitation status set to '.DnrStatus::from($data['dnr_status'])->label();
                break;

            case 'allergies':
                abort_unless($user->isClinical(), 403);
                $data = $request->validate(['allergies' => ['nullable', 'string', 'max:500'], 'nkda' => ['nullable', 'boolean']]);
                $list = $request->boolean('nkda') ? ['NKDA']
                    : array_values(array_filter(array_map('trim', explode(',', $data['allergies'] ?? ''))));
                $patient->update(['allergies' => $list ?: null]);
                $summary = 'Allergies updated: '.($list ? implode(', ', $list) : 'cleared');
                break;

            case 'critical':
                abort_unless($user->isDoctor(), 403);
                $data = $request->validate(['critical_flag' => ['required', 'boolean'], 'critical_reason' => ['nullable', 'required_if:critical_flag,1', 'string', 'max:200']]);
                $patient->update(['critical_flag' => (bool) $data['critical_flag'], 'critical_reason' => $data['critical_flag'] ? $data['critical_reason'] : null]);
                $summary = $data['critical_flag'] ? 'Flagged critical: '.$data['critical_reason'] : 'Critical flag removed';
                break;

            default:
                abort(404);
        }

        $encounter->entries()->create([
            'user_id' => $user->id, 'category' => EntryCategory::Note, 'action_key' => 'header',
            'type' => $field, 'summary' => $summary, 'payload' => $request->only(['reason']), 'recorded_at' => now(),
        ]);

        return back()->with('status', $summary.'.');
    }

    /** Sign / co-sign is inline on each entry. */
    public function cosign(Request $request, ClinicalEntry $entry)
    {
        abort_unless($entry->canBeCosignedBy($request->user()), 403);
        $entry->update(['cosigned_by' => $request->user()->id, 'cosigned_at' => now()]);

        return back()->with('status', 'Entry co-signed.');
    }

    /** Doctor acknowledges a nurse's alert. */
    public function acknowledge(Request $request, ClinicalEntry $entry)
    {
        abort_unless($request->user()->isDoctor() && $entry->category === EntryCategory::Alert && $entry->status === 'open', 403);
        $data = $request->validate(['response' => ['nullable', 'string', 'max:500']]);
        $entry->update([
            'status' => 'acknowledged',
            'payload' => array_merge($entry->payload ?? [], ['acknowledged_by' => $request->user()->name, 'acknowledged_at' => now()->toDateTimeString(), 'response' => $data['response'] ?? null]),
        ]);

        return back()->with('status', 'Alert acknowledged.');
    }

    /** Record a result against an investigation request. */
    public function result(Request $request, ClinicalEntry $entry)
    {
        abort_unless($request->user()->isClinical() && $entry->category === EntryCategory::Investigation && $entry->status !== 'resulted', 403);
        $data = $request->validate(['result' => ['required', 'string', 'max:2000'], 'abnormal' => ['nullable', 'boolean']]);
        $entry->update([
            'status' => 'resulted',
            'payload' => array_merge($entry->payload ?? [], ['result' => $data['result'], 'abnormal' => $request->boolean('abnormal'), 'resulted_by' => $request->user()->name, 'resulted_at' => now()->toDateTimeString()]),
        ]);

        return back()->with('status', 'Result recorded.');
    }

    public function cancelTransfer(Request $request, Transfer $transfer, PatientMovement $movement)
    {
        abort_unless($request->user()->isDoctor() && $transfer->status === Transfer::PENDING, 403);
        $movement->cancelTransfer($transfer, $request->user());
        $transfer->encounter->entries()->create([
            'user_id' => $request->user()->id, 'category' => EntryCategory::Movement, 'action_key' => 'refer_consult_transfer',
            'type' => 'cancel', 'summary' => 'Transfer to '.$transfer->toWard->name.' cancelled', 'recorded_at' => now(),
        ]);

        return back()->with('status', 'Transfer cancelled.');
    }
}
