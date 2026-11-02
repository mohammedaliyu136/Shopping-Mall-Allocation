<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BedLayout;
use App\Enums\ObsChartType;
use App\Enums\RolloutStage;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Http\Controllers\Controller;
use App\Models\Ward;
use App\Services\WardConfigRepository;
use App\Ward\Actions\ActionRegistry;
use App\Ward\Tabs\TabRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Phase 5: the admin setup screen. Checkboxes for buttons and tabs, dropdowns
 * for ward type and obs chart, and a bed count. Every change is logged.
 */
class WardSetupController extends Controller
{
    public function __construct(
        private WardConfigRepository $configs,
        private ActionRegistry $actions,
        private TabRegistry $tabs,
    ) {}

    public function index()
    {
        return view('admin.wards.index', [
            'wards' => Ward::with('config')->withCount('activeEncounters')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.wards.create', ['types' => WardType::cases()]);
    }

    /** A new ward works immediately: it gets its type's default config and beds. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:wards,code'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'ward_type' => ['required', Rule::enum(WardType::class)],
        ]);

        $ward = Ward::create($data);
        $this->configs->createDefault($ward, WardType::from($data['ward_type']), $request->user());

        return redirect()->route('admin.wards.edit', $ward)->with('status', "{$ward->name} created with the default ".WardType::from($data['ward_type'])->label().' setup. Adjust it below or leave it as is.');
    }

    public function edit(Ward $ward)
    {
        $config = $this->configs->for($ward);

        return view('admin.wards.edit', [
            'ward' => $ward,
            'config' => $config,
            'catalogue' => collect(WardType::cases())->mapWithKeys(fn (WardType $t) => [$t->value => [
                'doctor' => $this->actions->available(UserRole::Doctor, $t)->keys()->values(),
                'nurse' => $this->actions->available(UserRole::Nurse, $t)->keys()->values(),
                'tabs' => $this->tabs->available($t)->keys()->values(),
            ]]),
            'doctorActions' => $this->actions->all()->filter(fn ($a) => $a->role() === UserRole::Doctor),
            'nurseActions' => $this->actions->all()->filter(fn ($a) => $a->role() === UserRole::Nurse),
            'allTabs' => $this->tabs->all(),
            'changes' => $ward->configChanges()->limit(5)->get(),
        ]);
    }

    public function update(Request $request, Ward $ward)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'specialty' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'ward_type' => ['required', Rule::enum(WardType::class)],
            'bed_layout' => ['required', Rule::enum(BedLayout::class)],
            'bed_count' => ['required', 'integer', 'between:1,120'],
            'obs_chart_type' => ['required', Rule::enum(ObsChartType::class)],
            'obs_frequency_hours' => ['required', Rule::in(array_keys(config('ward.obs_frequencies')))],
            'rollout_stage' => ['required', Rule::enum(RolloutStage::class)],
            'enabled_tabs' => ['nullable', 'array'],
            'enabled_tabs.*' => [Rule::in(array_keys(TabRegistry::TABS))],
            'doctor_actions' => ['nullable', 'array'],
            'doctor_actions.*' => [Rule::in(array_keys(ActionRegistry::ACTIONS))],
            'nurse_actions' => ['nullable', 'array'],
            'nurse_actions.*' => [Rule::in(array_keys(ActionRegistry::ACTIONS))],
            'apply_defaults' => ['nullable', 'boolean'],
        ]);

        if (empty($data['apply_defaults']) && empty($data['enabled_tabs'])) {
            return back()->withInput()->withErrors(['enabled_tabs' => 'Enable at least one tab.']);
        }

        $ward->update(['name' => $data['name'], 'specialty' => $data['specialty'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $before = $ward->config?->version;
        $config = $this->configs->save($ward, $data, $request->user());

        return redirect()->route('admin.wards.edit', $ward)->with('status',
            $config->version === $before ? 'No configuration changes to save.' : "Saved. Configuration is now version {$config->version}; ward screens pick it up on their next load.");
    }

    public function history(Ward $ward)
    {
        return view('admin.wards.history', ['ward' => $ward, 'changes' => $ward->configChanges()->paginate(30)]);
    }
}
