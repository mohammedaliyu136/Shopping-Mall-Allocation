<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;
use Illuminate\Validation\Rule;

class Prescribe extends WardAction
{
    public function key(): string
    {
        return 'prescribe';
    }

    public function label(): string
    {
        return 'Prescribe';
    }

    public function contains(): string
    {
        return 'New medication · stop or modify · IV fluids · blood transfusion';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '💊';
    }

    public function types(): array
    {
        return [
            'new' => 'New medication',
            'stop_modify' => 'Stop or modify',
            'iv_fluids' => 'IV fluids',
            'transfusion' => 'Blood transfusion',
        ];
    }

    public function viewData(Encounter $encounter): array
    {
        return [
            'activeMeds' => $encounter->entries()
                ->where('category', EntryCategory::Medication->value)
                ->where('status', 'active')
                ->latest('recorded_at')->get(),
        ];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'drug' => ['required_if:type,new', 'nullable', 'string', 'max:120'],
            'dose' => ['required_if:type,new', 'nullable', 'string', 'max:60'],
            'route' => ['required_if:type,new', 'nullable', Rule::in(array_keys(config('ward.routes')))],
            'frequency' => ['required_if:type,new', 'nullable', Rule::in(array_keys(config('ward.frequencies')))],
            'duration' => ['nullable', 'string', 'max:60'],
            'indication' => ['nullable', 'string', 'max:255'],

            'prescription_id' => ['required_if:type,stop_modify', 'nullable', Rule::exists('clinical_entries', 'id')->where('encounter_id', $encounter->id)->where('status', 'active')],
            'change' => ['required_if:type,stop_modify', 'nullable', 'in:stop,modify'],
            'new_dose' => ['required_if:change,modify', 'nullable', 'string', 'max:60'],
            'new_frequency' => ['nullable', Rule::in(array_keys(config('ward.frequencies')))],
            'reason' => ['required_if:type,stop_modify', 'nullable', 'string', 'max:255'],

            'fluid' => ['required_if:type,iv_fluids', 'nullable', 'string', 'max:120'],
            'volume_ml' => ['required_if:type,iv_fluids', 'nullable', 'integer', 'min:1', 'max:5000'],
            'rate_ml_hr' => ['required_if:type,iv_fluids', 'nullable', 'integer', 'min:1', 'max:2000'],
            'additives' => ['nullable', 'string', 'max:120'],

            'product' => ['required_if:type,transfusion', 'nullable', Rule::in(array_keys(config('ward.blood_products')))],
            'units' => ['required_if:type,transfusion', 'nullable', 'integer', 'min:1', 'max:10'],
            'transfusion_rate' => ['nullable', 'string', 'max:60'],
            'transfusion_indication' => ['required_if:type,transfusion', 'nullable', 'string', 'max:255'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $routes = config('ward.routes');
        $freqs = config('ward.frequencies');

        switch ($data['type']) {
            case 'new':
                $this->record($encounter, $user, EntryCategory::Medication,
                    "{$data['drug']} {$data['dose']} {$data['route']} {$data['frequency']}",
                    [
                        'drug' => $data['drug'], 'dose' => $data['dose'], 'route' => $data['route'],
                        'route_label' => $routes[$data['route']], 'frequency' => $data['frequency'],
                        'frequency_label' => $freqs[$data['frequency']], 'infusion' => $data['frequency'] === 'CONT', 'duration' => $data['duration'] ?? null,
                        'indication' => $data['indication'] ?? null,
                    ],
                    ['type' => 'new', 'status' => 'active']);

                return "{$data['drug']} prescribed and added to the drug chart.";

            case 'stop_modify':
                $original = $encounter->entries()->findOrFail($data['prescription_id']);
                $drug = $original->data('drug') ?? $original->summary;

                if ($data['change'] === 'stop') {
                    $original->update(['status' => 'stopped']);
                    $this->record($encounter, $user, EntryCategory::Medication, "Stopped {$drug}: {$data['reason']}",
                        ['drug' => $drug, 'reason' => $data['reason']],
                        ['type' => 'stop', 'parent_id' => $original->id, 'status' => 'stopped']);

                    return "{$drug} stopped.";
                }

                $original->update(['status' => 'superseded']);
                $payload = array_merge($original->payload ?? [], [
                    'dose' => $data['new_dose'],
                    'frequency' => $data['new_frequency'] ?? $original->data('frequency'),
                    'frequency_label' => $freqs[$data['new_frequency'] ?? $original->data('frequency')] ?? null,
                    'reason' => $data['reason'],
                    'previous' => $original->summary,
                ]);
                $this->record($encounter, $user, EntryCategory::Medication,
                    "{$drug} {$payload['dose']} {$payload['route']} {$payload['frequency']} (modified)",
                    $payload, ['type' => 'modify', 'parent_id' => $original->id, 'status' => 'active']);

                return "{$drug} modified.";

            case 'iv_fluids':
                $this->record($encounter, $user, EntryCategory::Medication,
                    "IV {$data['fluid']} {$data['volume_ml']} ml at {$data['rate_ml_hr']} ml/hr",
                    [
                        'drug' => $data['fluid'], 'route' => 'IV', 'volume_ml' => (int) $data['volume_ml'],
                        'rate_ml_hr' => (int) $data['rate_ml_hr'], 'additives' => $data['additives'] ?? null,
                        'infusion' => true,
                    ],
                    ['type' => 'iv_fluids', 'status' => 'active']);

                return 'IV fluids prescribed.';

            case 'transfusion':
                $product = config('ward.blood_products')[$data['product']];
                $this->record($encounter, $user, EntryCategory::Medication,
                    "Transfuse {$data['units']} unit(s) {$product}",
                    [
                        'drug' => $product, 'route' => 'IV', 'units' => (int) $data['units'],
                        'rate' => $data['transfusion_rate'] ?? null, 'indication' => $data['transfusion_indication'],
                    ],
                    ['type' => 'transfusion', 'status' => 'active']);

                return "Transfusion of {$product} prescribed.";
        }

        return 'Saved.';
    }
}
