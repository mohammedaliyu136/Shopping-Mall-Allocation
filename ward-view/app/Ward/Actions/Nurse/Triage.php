<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\TriageCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;
use Illuminate\Validation\Rule;

/** Emergency only: assign a triage category; this sets the waiting-time target. */
class Triage extends WardAction
{
    public function key(): string
    {
        return 'triage';
    }

    public function label(): string
    {
        return 'Triage';
    }

    public function contains(): string
    {
        return 'Triage category · presenting complaint · re-triage';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '🏷️';
    }

    public function wardTypes(): array
    {
        return [WardType::Emergency];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'triage_category' => ['required', Rule::enum(TriageCategory::class)],
            'presenting_complaint' => ['required', 'string', 'max:255'],
            'pain_score' => ['nullable', 'integer', 'between:0,10'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $category = TriageCategory::from((int) $data['triage_category']);
        $retriage = $encounter->triage_category !== null;

        $encounter->update([
            'triage_category' => $category,
            'triaged_at' => $encounter->triaged_at ?? now(),
            'presenting_complaint' => $data['presenting_complaint'],
        ]);

        $this->record($encounter, $user, EntryCategory::Triage,
            ($retriage ? 'Re-triaged ' : 'Triaged ').$category->label().": {$data['presenting_complaint']}",
            ['category' => $category->value, 'presenting_complaint' => $data['presenting_complaint'], 'pain_score' => $data['pain_score'] ?? null, 'notes' => $data['notes'] ?? null],
            ['type' => $retriage ? 'retriage' : 'triage']);

        return 'Triage saved: '.$category->label().'. Target time to be seen: '.$category->targetMinutes().' min.';
    }
}
