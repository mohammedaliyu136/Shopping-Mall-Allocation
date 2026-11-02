<?php

namespace App\Services;

use App\Enums\EncounterStatus;
use App\Enums\EntryCategory;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving patients between beds, teams and wards. A transfer to a ward is a
 * request until a nurse on the receiving ward accepts it with Move Patient.
 */
class PatientMovement
{
    public function requestWardTransfer(Encounter $encounter, Ward $to, User $by, string $type, ?string $reason, ?int $toTeamId = null): Transfer
    {
        if ($encounter->pendingTransfer) {
            throw ValidationException::withMessages(['to_ward_id' => 'A transfer is already pending for this patient. Cancel it first.']);
        }
        if ($to->is($encounter->ward)) {
            throw ValidationException::withMessages(['to_ward_id' => 'The patient is already on this ward.']);
        }

        return $encounter->transfers()->create([
            'type' => $type,
            'from_ward_id' => $encounter->ward_id,
            'to_ward_id' => $to->id,
            'from_team_id' => $encounter->team_id,
            'to_team_id' => $toTeamId,
            'reason' => $reason,
            'requested_by' => $by->id,
            'status' => Transfer::PENDING,
        ]);
    }

    public function transferTeam(Encounter $encounter, int $teamId, User $by, ?string $reason): Transfer
    {
        return DB::transaction(function () use ($encounter, $teamId, $by, $reason) {
            $transfer = $encounter->transfers()->create([
                'type' => Transfer::TYPE_TEAM,
                'from_ward_id' => $encounter->ward_id,
                'from_team_id' => $encounter->team_id,
                'to_team_id' => $teamId,
                'reason' => $reason,
                'requested_by' => $by->id,
                'status' => Transfer::COMPLETED,
                'completed_by' => $by->id,
                'completed_at' => now(),
            ]);
            $encounter->update(['team_id' => $teamId]);

            return $transfer;
        });
    }

    /** Receiving nurse accepts the patient onto a bed on their ward. */
    public function acceptTransfer(Transfer $transfer, Bed $bed, User $by): void
    {
        if ($transfer->status !== Transfer::PENDING) {
            throw ValidationException::withMessages(['transfer_id' => 'This transfer is no longer pending.']);
        }
        if ($bed->ward_id !== $transfer->to_ward_id || $bed->occupant()->exists()) {
            throw ValidationException::withMessages(['bed_id' => 'Choose an empty bed on the receiving ward.']);
        }

        DB::transaction(function () use ($transfer, $bed, $by) {
            $encounter = $transfer->encounter;
            $fromWard = $encounter->ward;
            $wasAttendance = $encounter->status === EncounterStatus::Waiting;

            $encounter->update([
                'ward_id' => $bed->ward_id,
                'bed_id' => $bed->id,
                'team_id' => $transfer->to_team_id ?? $encounter->team_id,
                'status' => EncounterStatus::Admitted,
                'admitted_at' => $encounter->admitted_at ?? now(),
                'assigned_nurse_id' => null,
                'off_ward' => null,
                'obs_frequency_hours' => null,
            ]);
            $transfer->update(['status' => Transfer::COMPLETED, 'completed_by' => $by->id, 'completed_at' => now()]);

            $encounter->entries()->create([
                'user_id' => $by->id,
                'category' => EntryCategory::Movement,
                'action_key' => 'move_patient',
                'type' => 'accept_transfer',
                'summary' => ($wasAttendance ? 'Admitted from ' : 'Transferred in from ').$fromWard->name.' to '.$bed->ward->name.', bed '.$bed->label,
                'payload' => ['from_ward' => $fromWard->name, 'to_ward' => $bed->ward->name, 'bed' => $bed->label, 'transfer_id' => $transfer->id],
                'recorded_at' => now(),
            ]);
        });
    }

    public function cancelTransfer(Transfer $transfer, User $by): void
    {
        $transfer->update(['status' => Transfer::CANCELLED, 'completed_by' => $by->id, 'completed_at' => now()]);
    }

    /** Close the stay and free the bed. */
    public function close(Encounter $encounter, string $dischargeType): void
    {
        $encounter->transfers()->where('status', Transfer::PENDING)->update(['status' => Transfer::CANCELLED]);
        $encounter->update([
            'status' => EncounterStatus::Discharged,
            'discharge_type' => $dischargeType,
            'discharged_at' => now(),
            'bed_id' => null,
            'assigned_nurse_id' => null,
            'off_ward' => null,
        ]);
    }
}
