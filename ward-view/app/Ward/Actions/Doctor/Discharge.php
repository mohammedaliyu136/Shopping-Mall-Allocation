<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;

/**
 * Discharge absorbs "mark as deceased": the type selector records the kind of
 * discharge. Starting a discharge opens the nurse discharge checklist; the
 * stay closes when the checklist is complete.
 */
class Discharge extends WardAction
{
    public function key(): string
    {
        return 'discharge';
    }

    public function label(): string
    {
        return 'Discharge';
    }

    public function contains(): string
    {
        return 'Home · against medical advice · to another facility · deceased';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '🏠';
    }

    public function wardTypes(): array
    {
        return [WardType::General, WardType::CriticalCare];
    }

    public function types(): array
    {
        return [
            'home' => 'Home',
            'ama' => 'Against medical advice',
            'facility' => 'Transfer to another facility',
            'deceased' => 'Deceased',
        ];
    }

    public function unavailableReason(Encounter $encounter): ?string
    {
        return match (true) {
            $encounter->isDischargePending() => 'Discharge already started; nurses are completing the checklist.',
            ! $encounter->isActive() => 'Patient has left the ward.',
            default => null,
        };
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'summary' => ['required_unless:type,deceased', 'nullable', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:500'],
            'discharge_medication' => ['nullable', 'string', 'max:2000'],
            'facility' => ['required_if:type,facility', 'nullable', 'string', 'max:150'],
            'risks_explained' => ['accepted_if:type,ama'],
            'time_of_death' => ['required_if:type,deceased', 'nullable', 'date', 'before_or_equal:now'],
            'cause_of_death' => ['required_if:type,deceased', 'nullable', 'string', 'max:500'],
            'verified_by' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $type = $data['type'];
        $checklistKey = in_array($type, ['facility', 'deceased'], true) ? $type : 'default';
        $checklist = collect(config("ward.discharge_checklists.{$checklistKey}"))
            ->map(fn ($label) => ['label' => $label, 'done' => false, 'by' => null, 'at' => null])
            ->all();

        $encounter->update([
            'status' => EncounterStatus::DischargePending,
            'discharge_type' => $type,
            'discharge_checklist' => $checklist,
            'discharge_started_at' => now(),
        ]);

        $summary = match ($type) {
            'deceased' => 'Patient died at '.date('H:i j M', strtotime($data['time_of_death'])).": {$data['cause_of_death']}",
            'facility' => "Discharge to {$data['facility']} started",
            'ama' => 'Self-discharge against medical advice; risks explained',
            default => 'Discharge home started',
        };

        $this->record($encounter, $user, EntryCategory::Discharge, $summary, [
            'discharge_type' => $this->typeLabel($type),
            'summary' => $data['summary'] ?? null,
            'follow_up' => $data['follow_up'] ?? null,
            'discharge_medication' => $data['discharge_medication'] ?? null,
            'facility' => $data['facility'] ?? null,
            'time_of_death' => $data['time_of_death'] ?? null,
            'cause_of_death' => $data['cause_of_death'] ?? null,
            'verified_by' => $data['verified_by'] ?? null,
        ], ['type' => $type, 'status' => 'started']);

        return 'Discharge started. Nurses now have the discharge checklist for this patient.';
    }
}
