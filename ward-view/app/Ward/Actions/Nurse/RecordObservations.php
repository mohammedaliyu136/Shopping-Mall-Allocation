<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\ClinicalEntry;
use App\Models\Encounter;
use App\Models\User;
use App\Services\EarlyWarningScore;
use App\Ward\Actions\WardAction;
use Illuminate\Support\Collection;

/**
 * Vitals, early warning score, pain score and fluid input/output. On critical
 * care wards the same form adds ventilator settings and infusion rates.
 */
class RecordObservations extends WardAction
{
    public function __construct(private EarlyWarningScore $news) {}

    public function key(): string
    {
        return 'record_observations';
    }

    public function label(): string
    {
        return 'Record Observations';
    }

    public function contains(): string
    {
        return 'Vitals · early warning score · pain score · fluid input/output';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '❤️';
    }

    public static function infusions(Encounter $encounter): Collection
    {
        return $encounter->entries()
            ->where('category', EntryCategory::Medication->value)
            ->where('status', 'active')
            ->get()
            ->filter(fn (ClinicalEntry $e) => $e->data('infusion') || $e->type === 'transfusion')
            ->values();
    }

    public function viewData(Encounter $encounter): array
    {
        $critical = $encounter->ward->type() === WardType::CriticalCare;

        return [
            'critical' => $critical,
            'infusions' => $critical ? self::infusions($encounter) : collect(),
            'lastVent' => $critical ? $encounter->latestObservation?->data('ventilator') : null,
        ];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'respiratory_rate' => ['required', 'integer', 'between:0,80'],
            'spo2' => ['required', 'integer', 'between:50,100'],
            'on_oxygen' => ['required', 'boolean'],
            'oxygen_flow' => ['nullable', 'string', 'max:30'],
            'systolic_bp' => ['required', 'integer', 'between:30,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:10,200'],
            'heart_rate' => ['required', 'integer', 'between:20,250'],
            'consciousness' => ['required', 'in:'.implode(',', array_keys(config('ward.consciousness')))],
            'temperature' => ['required', 'numeric', 'between:30,44'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'blood_glucose' => ['nullable', 'numeric', 'between:0,60'],
            'fluid_in_ml' => ['nullable', 'integer', 'between:0,10000'],
            'fluid_out_ml' => ['nullable', 'integer', 'between:0,10000'],
            'output_route' => ['nullable', 'in:urine,drain,vomit,stool,ng'],

            'vent_mode' => ['nullable', 'in:'.implode(',', array_keys(config('ward.ventilation_modes')))],
            'fio2' => ['nullable', 'integer', 'between:21,100'],
            'peep' => ['nullable', 'numeric', 'between:0,30'],
            'tidal_volume' => ['nullable', 'integer', 'between:0,2000'],
            'set_rate' => ['nullable', 'integer', 'between:0,60'],
            'infusion_rates' => ['nullable', 'array'],
            'infusion_rates.*' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $result = $this->news->calculate($data);

        $ventilator = null;
        if (! empty($data['vent_mode'])) {
            $ventilator = array_filter([
                'mode' => config('ward.ventilation_modes')[$data['vent_mode']],
                'mode_key' => $data['vent_mode'],
                'fio2' => $data['fio2'] ?? null,
                'peep' => $data['peep'] ?? null,
                'tidal_volume' => $data['tidal_volume'] ?? null,
                'set_rate' => $data['set_rate'] ?? null,
            ], fn ($v) => $v !== null);
        }

        $infusions = [];
        if (! empty($data['infusion_rates'])) {
            $running = self::infusions($encounter)->keyBy('id');
            foreach ($data['infusion_rates'] as $id => $rate) {
                if ($rate !== null && $rate !== '' && $running->has($id)) {
                    $infusions[] = ['prescription_id' => (int) $id, 'drug' => $running[$id]->data('drug'), 'rate' => $rate];
                }
            }
        }

        $vitals = "RR {$data['respiratory_rate']}, SpO₂ {$data['spo2']}%".($data['on_oxygen'] ? ' on O₂' : '')
            .", BP {$data['systolic_bp']}".(isset($data['diastolic_bp']) ? "/{$data['diastolic_bp']}" : '')
            .", HR {$data['heart_rate']}, T {$data['temperature']}°, {$data['consciousness']}";

        $this->record($encounter, $user, EntryCategory::Observation, "NEWS2 {$result['score']} — {$vitals}", [
            'respiratory_rate' => (int) $data['respiratory_rate'],
            'spo2' => (int) $data['spo2'],
            'on_oxygen' => (bool) $data['on_oxygen'],
            'oxygen_flow' => $data['oxygen_flow'] ?? null,
            'systolic_bp' => (int) $data['systolic_bp'],
            'diastolic_bp' => isset($data['diastolic_bp']) ? (int) $data['diastolic_bp'] : null,
            'heart_rate' => (int) $data['heart_rate'],
            'consciousness' => $data['consciousness'],
            'temperature' => (float) $data['temperature'],
            'pain_score' => isset($data['pain_score']) ? (int) $data['pain_score'] : null,
            'blood_glucose' => $data['blood_glucose'] ?? null,
            'fluid_in_ml' => isset($data['fluid_in_ml']) ? (int) $data['fluid_in_ml'] : null,
            'fluid_out_ml' => isset($data['fluid_out_ml']) ? (int) $data['fluid_out_ml'] : null,
            'output_route' => $data['output_route'] ?? null,
            'news_parts' => $result['parts'],
            'band' => $result['band'],
            'ventilator' => $ventilator,
            'infusions' => $infusions,
        ], ['score' => $result['score'], 'status' => $result['band']]);

        $message = "Observations saved. NEWS2 {$result['score']} ({$result['band']}).";

        if ($this->news->needsEscalation($result['band'])) {
            session()->flash('escalate', $this->news->response($result['band']));
        }

        return $message;
    }
}
