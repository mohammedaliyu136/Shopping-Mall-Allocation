<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Encounter;
use App\Models\User;
use App\Ward\Actions\WardAction;

/** SBAR escalation. Open alerts show in the patient header and on the ward list. */
class AlertDoctor extends WardAction
{
    public function key(): string
    {
        return 'alert_doctor';
    }

    public function label(): string
    {
        return 'Alert Doctor';
    }

    public function contains(): string
    {
        return 'Escalate a deteriorating patient · request a review';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '🚨';
    }

    public function types(): array
    {
        return ['deteriorating' => 'Deteriorating patient', 'review' => 'Request a review'];
    }

    public function viewData(Encounter $encounter): array
    {
        return ['latestObs' => $encounter->latestObservation];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'urgency' => ['required', 'in:'.implode(',', array_keys(config('ward.urgencies')))],
            'situation' => ['required', 'string', 'max:1000'],
            'background' => ['nullable', 'string', 'max:1000'],
            'assessment' => ['nullable', 'string', 'max:1000'],
            'recommendation' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        $urgency = config('ward.urgencies')[$data['urgency']];
        $label = $this->typeLabel($data['type']);
        $obs = $encounter->latestObservation;

        $this->record($encounter, $user, EntryCategory::Alert, "{$label} ({$urgency}): {$data['situation']}", [
            'urgency' => $data['urgency'],
            'situation' => $data['situation'],
            'background' => $data['background'] ?? null,
            'assessment' => $data['assessment'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'news2' => $obs?->score,
        ], ['type' => $data['type'], 'status' => 'open']);

        return "Doctor alerted ({$urgency}). The alert shows on the patient header until a doctor acknowledges it.";
    }
}
