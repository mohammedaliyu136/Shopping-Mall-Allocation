<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;

class NursingNote extends WardAction
{
    public function key(): string
    {
        return 'nursing_note';
    }

    public function label(): string
    {
        return 'Nursing Note';
    }

    public function contains(): string
    {
        return 'Shift note · care given · wound care · patient concerns';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '🗒️';
    }

    public function types(): array
    {
        return ['shift' => 'Shift note', 'care' => 'Care given', 'wound' => 'Wound care', 'concern' => 'Patient concern'];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'note' => ['required', 'string', 'max:5000'],
            'care_items' => ['nullable', 'array'],
            'care_items.*' => ['in:hygiene,mouth_care,repositioned,mobilised,fed,continence,catheter_care,line_care'],
            'wound_site' => ['required_if:type,wound', 'nullable', 'string', 'max:120'],
            'wound_appearance' => ['nullable', 'in:clean,sloughy,necrotic,granulating,infected'],
            'dressing' => ['nullable', 'string', 'max:120'],
            'next_dressing' => ['nullable', 'date'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $label = $this->typeLabel($data['type']);
        $this->record($encounter, $user, EntryCategory::Note, "{$label}: {$data['note']}", [
            'note' => $data['note'],
            'care_items' => $data['care_items'] ?? null,
            'wound_site' => $data['wound_site'] ?? null,
            'wound_appearance' => $data['wound_appearance'] ?? null,
            'dressing' => $data['dressing'] ?? null,
            'next_dressing' => $data['next_dressing'] ?? null,
        ], ['type' => $data['type']]);

        if ($data['type'] === 'wound' && ! empty($data['next_dressing'])) {
            $encounter->ward->tasks()->create([
                'encounter_id' => $encounter->id,
                'title' => "Redress wound ({$data['wound_site']})",
                'for_role' => UserRole::Nurse->value,
                'due_at' => $data['next_dressing'],
                'created_by' => $user->id,
            ]);
        }

        return "{$label} saved.";
    }
}
