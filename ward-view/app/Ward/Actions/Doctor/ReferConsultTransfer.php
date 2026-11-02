<?php

namespace App\Ward\Actions\Doctor;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Ward;
use App\Services\PatientMovement;
use App\Ward\Actions\WardAction;
use Illuminate\Support\Facades\DB;

/**
 * One form, four types. A consult keeps the patient with the current team;
 * a transfer moves ownership to another team or ward.
 */
class ReferConsultTransfer extends WardAction
{
    public function __construct(private PatientMovement $movement) {}

    public function key(): string
    {
        return 'refer_consult_transfer';
    }

    public function label(): string
    {
        return 'Refer, Consult, Transfer';
    }

    public function contains(): string
    {
        return 'Consult only · transfer to team · transfer to ward · escalate to ICU/HDU';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '🔀';
    }

    public function types(): array
    {
        return [
            'consult' => 'Consult only (patient stays with your team)',
            'transfer_team' => 'Transfer to another team',
            'transfer_ward' => 'Transfer to another ward',
            'escalate' => 'Escalate to ICU / HDU',
        ];
    }

    public function viewData(Encounter $encounter): array
    {
        $wards = Ward::query()->where('is_active', true)->whereKeyNot($encounter->ward_id)
            ->with('config')->orderBy('name')->get();

        return [
            'teams' => Team::orderBy('name')->get(),
            'generalWards' => $wards->filter(fn ($w) => $w->type() === WardType::General),
            'criticalWards' => $wards->filter(fn ($w) => $w->type() === WardType::CriticalCare),
            'pending' => $encounter->pendingTransfer,
        ];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'team_id' => ['required_if:type,consult,transfer_team', 'nullable', 'exists:teams,id'],
            'to_ward_id' => ['required_if:type,transfer_ward,escalate', 'nullable', 'exists:wards,id'],
            'to_team_id' => ['nullable', 'exists:teams,id'],
            'question' => ['required_if:type,consult', 'nullable', 'string', 'max:1000'],
            'reason' => ['required_unless:type,consult', 'nullable', 'string', 'max:1000'],
            'urgency' => ['required', 'in:'.implode(',', array_keys(config('ward.urgencies')))],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        return DB::transaction(function () use ($encounter, $user, $data) {
            $urgency = config('ward.urgencies')[$data['urgency']];

            switch ($data['type']) {
                case 'consult':
                    $team = Team::findOrFail($data['team_id']);
                    $this->record($encounter, $user, EntryCategory::Movement, "Consult requested from {$team->name} ({$urgency}): {$data['question']}",
                        ['team' => $team->name, 'question' => $data['question'], 'urgency' => $data['urgency'], 'ownership' => 'Stays with current team'],
                        ['type' => 'consult', 'status' => 'requested']);

                    return "Consult requested from {$team->name}. The patient stays under your team.";

                case 'transfer_team':
                    $team = Team::findOrFail($data['team_id']);
                    $from = $encounter->team?->name ?? 'no team';
                    $this->movement->transferTeam($encounter, $team->id, $user, $data['reason']);
                    $this->record($encounter, $user, EntryCategory::Movement, "Care transferred from {$from} to {$team->name}: {$data['reason']}",
                        ['from_team' => $from, 'to_team' => $team->name, 'reason' => $data['reason'], 'ownership' => 'Moved to '.$team->name],
                        ['type' => 'transfer_team', 'status' => 'completed']);

                    return "Patient is now under {$team->name}.";

                case 'transfer_ward':
                case 'escalate':
                    $ward = Ward::with('config')->findOrFail($data['to_ward_id']);
                    $escalate = $data['type'] === 'escalate';
                    $this->movement->requestWardTransfer($encounter, $ward, $user,
                        $escalate ? Transfer::TYPE_ESCALATION : Transfer::TYPE_WARD, $data['reason'], $data['to_team_id'] ?? null);

                    $this->record($encounter, $user, $escalate ? EntryCategory::Alert : EntryCategory::Movement,
                        ($escalate ? 'Escalated to ' : 'Transfer requested to ').$ward->name." ({$urgency}): {$data['reason']}",
                        ['to_ward' => $ward->name, 'reason' => $data['reason'], 'urgency' => $data['urgency'], 'ownership' => 'Moves to '.$ward->name.' when accepted'],
                        ['type' => $data['type'], 'status' => 'pending']);

                    if ($escalate) {
                        $encounter->patient->update(['critical_flag' => true, 'critical_reason' => 'Escalated to '.$ward->name]);
                    }

                    return "Transfer to {$ward->name} requested. A nurse on {$ward->name} will accept the patient with Move Patient.";
            }

            return 'Saved.';
        });
    }
}
