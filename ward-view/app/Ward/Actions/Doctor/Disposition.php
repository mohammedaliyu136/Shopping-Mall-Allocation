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

/** Emergency only: the end of an A&E attendance — admit, discharge or refer. */
class Disposition extends WardAction
{
    public function __construct(private PatientMovement $movement) {}

    public function key(): string
    {
        return 'disposition';
    }

    public function label(): string
    {
        return 'Disposition';
    }

    public function contains(): string
    {
        return 'Admit to ward · discharge from A&E · refer out';
    }

    public function role(): UserRole
    {
        return UserRole::Doctor;
    }

    public function icon(): string
    {
        return '🚪';
    }

    public function wardTypes(): array
    {
        return [WardType::Emergency];
    }

    public function types(): array
    {
        return ['admit' => 'Admit to a ward', 'discharge' => 'Discharge from A&E', 'refer' => 'Refer out (clinic / other facility)'];
    }

    public function viewData(Encounter $encounter): array
    {
        return [
            'wards' => Ward::query()->where('is_active', true)->whereKeyNot($encounter->ward_id)->with('config')->orderBy('name')->get()
                ->filter(fn ($w) => $w->type() !== WardType::Emergency),
            'teams' => Team::orderBy('name')->get(),
            'pending' => $encounter->pendingTransfer,
        ];
    }

    public function unavailableReason(Encounter $encounter): ?string
    {
        if ($encounter->pendingTransfer) {
            return 'Admission requested to '.$encounter->pendingTransfer->toWard->name.'; waiting for the ward to accept.';
        }

        return parent::unavailableReason($encounter);
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'to_ward_id' => ['required_if:type,admit', 'nullable', 'exists:wards,id'],
            'team_id' => ['required_if:type,admit', 'nullable', 'exists:teams,id'],
            'refer_to' => ['required_if:type,refer', 'nullable', 'string', 'max:150'],
            'diagnosis' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        return DB::transaction(function () use ($encounter, $user, $data) {
            $this->markSeen($encounter, $user);
            $encounter->update(['disposition' => $data['type'], 'diagnoses' => [$data['diagnosis']]]);

            if ($data['type'] === 'admit') {
                $ward = Ward::findOrFail($data['to_ward_id']);
                $team = Team::findOrFail($data['team_id']);
                $this->movement->requestWardTransfer($encounter, $ward, $user, Transfer::TYPE_ADMISSION, $data['notes'] ?? null, $team->id);
                $this->record($encounter, $user, EntryCategory::Movement, "Admit to {$ward->name} under {$team->name}: {$data['diagnosis']}",
                    ['to_ward' => $ward->name, 'team' => $team->name, 'diagnosis' => $data['diagnosis'], 'notes' => $data['notes'] ?? null],
                    ['type' => 'admit', 'status' => 'pending']);

                return "Admission to {$ward->name} requested. The patient stays on the A&E board until the ward accepts.";
            }

            $summary = $data['type'] === 'refer'
                ? "Referred to {$data['refer_to']}: {$data['diagnosis']}"
                : "Discharged from A&E: {$data['diagnosis']}";
            $this->record($encounter, $user, EntryCategory::Discharge, $summary,
                ['diagnosis' => $data['diagnosis'], 'refer_to' => $data['refer_to'] ?? null, 'notes' => $data['notes'] ?? null],
                ['type' => $data['type'], 'status' => 'completed']);
            $this->movement->close($encounter, $data['type'] === 'refer' ? 'referred' : 'ed_discharge');

            return $data['type'] === 'refer' ? 'Patient referred out and removed from the board.' : 'Patient discharged from A&E.';
        });
    }
}
