<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;
use Illuminate\Validation\Rule;

class AdministerMedication extends WardAction
{
    public function key(): string
    {
        return 'administer_medication';
    }

    public function label(): string
    {
        return 'Administer Medication';
    }

    public function contains(): string
    {
        return 'Give · delay · document a missed dose — from the drug chart';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '💉';
    }

    public function types(): array
    {
        return ['given' => 'Given', 'delayed' => 'Delayed', 'missed' => 'Missed / not given'];
    }

    public function viewData(Encounter $encounter): array
    {
        $chart = $encounter->entries()
            ->where('category', EntryCategory::Medication->value)
            ->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('category', EntryCategory::Administration->value)->latest('recorded_at')])
            ->orderBy('recorded_at')->get();

        return ['chart' => $chart];
    }

    public function unavailableReason(Encounter $encounter): ?string
    {
        if (! $encounter->entries()->where('category', EntryCategory::Medication->value)->where('status', 'active')->exists()) {
            return 'Nothing on the drug chart yet.';
        }

        return parent::unavailableReason($encounter);
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'prescription_id' => ['required', Rule::exists('clinical_entries', 'id')->where('encounter_id', $encounter->id)->where('status', 'active')],
            'dose_given' => ['nullable', 'string', 'max:60'],
            'given_at' => ['required_if:type,given', 'nullable', 'date', 'before_or_equal:now'],
            'reason' => ['required_unless:type,given', 'nullable', 'in:'.implode(',', array_keys(config('ward.administration_reasons')))],
            'delay_until' => ['required_if:type,delayed', 'nullable', 'date_format:H:i'],
            'witness' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $rx = $encounter->entries()->findOrFail($data['prescription_id']);
        $drug = $rx->data('drug') ?? $rx->summary;
        $reason = isset($data['reason']) ? config('ward.administration_reasons')[$data['reason']] : null;

        $summary = match ($data['type']) {
            'given' => "Given: {$drug} ".($data['dose_given'] ?? $rx->data('dose')),
            'delayed' => "Delayed: {$drug} until {$data['delay_until']} ({$reason})",
            'missed' => "Not given: {$drug} ({$reason})",
        };

        $this->record($encounter, $user, EntryCategory::Administration, $summary, [
            'drug' => $drug,
            'dose' => $data['dose_given'] ?? $rx->data('dose'),
            'given_at' => $data['given_at'] ?? null,
            'reason' => $reason,
            'delay_until' => $data['delay_until'] ?? null,
            'witness' => $data['witness'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], [
            'type' => $data['type'],
            'parent_id' => $rx->id,
            'status' => $data['type'],
            'recorded_at' => $data['type'] === 'given' && ! empty($data['given_at']) ? $data['given_at'] : now(),
        ]);

        return $summary.'.';
    }
}
