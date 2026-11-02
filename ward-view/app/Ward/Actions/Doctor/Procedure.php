<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;

class Procedure extends WardAction
{
    public function key(): string
    {
        return 'procedure';
    }

    public function label(): string
    {
        return 'Procedure';
    }

    public function contains(): string
    {
        return 'Order a procedure · book theatre';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '🩺';
    }

    public function types(): array
    {
        return ['order' => 'Order a procedure', 'theatre' => 'Book theatre'];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'procedure' => ['required', 'string', 'max:150'],
            'urgency' => ['required', 'in:'.implode(',', array_keys(config('ward.urgencies')))],
            'indication' => ['required', 'string', 'max:500'],
            'consent' => ['nullable', 'boolean'],
            'theatre_date' => ['required_if:type,theatre', 'nullable', 'date', 'after_or_equal:today'],
            'anaesthetic' => ['required_if:type,theatre', 'nullable', 'in:general,regional,local,sedation'],
            'surgeon' => ['nullable', 'string', 'max:120'],
            'fasting_from' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $isTheatre = $data['type'] === 'theatre';
        $summary = $isTheatre
            ? "Theatre booked: {$data['procedure']} on ".date('j M', strtotime($data['theatre_date']))
            : "Procedure ordered: {$data['procedure']}";

        $this->record($encounter, $user, EntryCategory::Procedure, $summary, [
            'procedure' => $data['procedure'],
            'urgency' => $data['urgency'],
            'indication' => $data['indication'],
            'consent' => ! empty($data['consent']) ? 'Consent obtained' : 'Consent outstanding',
            'theatre_date' => $data['theatre_date'] ?? null,
            'anaesthetic' => $data['anaesthetic'] ?? null,
            'surgeon' => $data['surgeon'] ?? null,
            'fasting_from' => $data['fasting_from'] ?? null,
        ], ['type' => $data['type'], 'status' => $isTheatre ? 'booked' : 'ordered']);

        if ($isTheatre && ! empty($data['fasting_from'])) {
            $encounter->ward->tasks()->create([
                'encounter_id' => $encounter->id,
                'title' => "Nil by mouth from {$data['fasting_from']} for theatre ({$data['procedure']})",
                'for_role' => UserRole::Nurse->value,
                'due_at' => now()->setTimeFromTimeString($data['fasting_from']),
                'created_by' => $user->id,
            ]);
        }
        $this->markSeen($encounter, $user);

        return $isTheatre ? 'Theatre booked.' : 'Procedure ordered.';
    }
}
