<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;

class RequestInvestigation extends WardAction
{
    public function key(): string
    {
        return 'request';
    }

    public function label(): string
    {
        return 'Request';
    }

    public function contains(): string
    {
        return 'Labs · radiology · physio/OT · dietitian · other services';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '🧪';
    }

    public function types(): array
    {
        return [
            'lab' => 'Laboratory',
            'radiology' => 'Radiology / scan',
            'physio_ot' => 'Physio / OT',
            'dietitian' => 'Dietitian',
            'other' => 'Other service',
        ];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'tests' => ['required_if:type,lab', 'nullable', 'array', 'min:1'],
            'tests.*' => ['string', 'max:80'],
            'other_test' => ['nullable', 'string', 'max:80'],
            'item' => ['required_unless:type,lab', 'nullable', 'string', 'max:120'],
            'urgency' => ['required', 'in:'.implode(',', array_keys(config('ward.urgencies')))],
            'clinical_details' => ['required', 'string', 'max:1000'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $urgency = config('ward.urgencies')[$data['urgency']];
        $typeLabel = $this->typeLabel($data['type']);

        if ($data['type'] === 'lab') {
            $tests = array_values(array_filter(array_merge($data['tests'] ?? [], [$data['other_test'] ?? null])));
            foreach ($tests as $test) {
                $this->record($encounter, $user, EntryCategory::Investigation, "{$test} ({$urgency})",
                    ['item' => $test, 'urgency' => $data['urgency'], 'clinical_details' => $data['clinical_details'], 'kind' => $typeLabel],
                    ['type' => 'lab', 'status' => 'requested']);
            }
            $this->markSeen($encounter, $user);

            return count($tests).' lab test(s) requested. Nurses will see them under Collect Sample.';
        }

        $this->record($encounter, $user, EntryCategory::Investigation, "{$typeLabel}: {$data['item']} ({$urgency})",
            ['item' => $data['item'], 'urgency' => $data['urgency'], 'clinical_details' => $data['clinical_details'], 'kind' => $typeLabel],
            ['type' => $data['type'], 'status' => 'requested']);
        $this->markSeen($encounter, $user);

        return "{$typeLabel} request sent.";
    }
}
