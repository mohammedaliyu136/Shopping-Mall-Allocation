<?php

namespace App\Ward\Actions\Nurse;

use App\Enums\EntryCategory;
use App\Enums\UserRole;
use App\Models\Bed;
use App\Models\Encounter;
use App\Models\Transfer;
use App\Models\User;
use App\Services\PatientMovement;
use App\Ward\Actions\WardAction;
use Illuminate\Validation\ValidationException;

/** Bed change, accepting a transfer, or sending the patient off the ward and back. */
class MovePatient extends WardAction
{
    public function __construct(private PatientMovement $movement) {}

    public function key(): string
    {
        return 'move_patient';
    }

    public function label(): string
    {
        return 'Move Patient';
    }

    public function contains(): string
    {
        return 'Bed change · accept a transfer · send for scan or theatre · return to ward';
    }

    public function role(): UserRole
    {
        return UserRole::Nurse;
    }

    public function icon(): string
    {
        return '🛏️';
    }

    public function types(): array
    {
        return [
            'bed_change' => 'Bed change',
            'accept_transfer' => 'Accept transfer onto this ward',
            'off_ward' => 'Send for scan / theatre',
            'returned' => 'Returned to ward',
        ];
    }

    /** Accepting is offered on the receiving ward; the other moves on the patient's own ward. */
    public function typesFor(Encounter $encounter): array
    {
        $transfer = $encounter->pendingTransfer;
        if ($transfer && session('ward_id') === $transfer->to_ward_id) {
            return ['accept_transfer' => $this->types()['accept_transfer']];
        }
        $types = $this->types();
        unset($types['accept_transfer']);
        if ($encounter->off_ward) {
            unset($types['off_ward']);
        } else {
            unset($types['returned']);
        }

        return $types;
    }

    public function viewData(Encounter $encounter): array
    {
        $transfer = $encounter->pendingTransfer;
        $emptyBeds = fn (int $wardId) => Bed::where('ward_id', $wardId)->where('is_active', true)
            ->whereDoesntHave('occupant')->orderBy('position')->get();

        return [
            'transfer' => $transfer,
            'emptyBeds' => $emptyBeds($encounter->ward_id),
            'receivingBeds' => $transfer ? $emptyBeds($transfer->to_ward_id) : collect(),
        ];
    }

    public function rules(Encounter $encounter): array
    {
        return [
            'type' => $this->typeRule(),
            'bed_id' => ['required_if:type,bed_change,accept_transfer', 'nullable', 'exists:beds,id'],
            'destination' => ['required_if:type,off_ward', 'nullable', 'in:'.implode(',', array_keys(config('ward.off_ward_destinations')))],
            'escort' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function handle(Encounter $encounter, User $user, array $data): string
    {
        switch ($data['type']) {
            case 'bed_change':
                $bed = Bed::findOrFail($data['bed_id']);
                if ($bed->ward_id !== $encounter->ward_id || $bed->occupant()->exists()) {
                    throw ValidationException::withMessages(['bed_id' => 'Choose an empty bed on this ward.']);
                }
                $from = $encounter->bed?->label ?? 'no bed';
                $encounter->update(['bed_id' => $bed->id]);
                $this->record($encounter, $user, EntryCategory::Movement, "Moved from bed {$from} to bed {$bed->label}",
                    ['from_bed' => $from, 'to_bed' => $bed->label, 'notes' => $data['notes'] ?? null], ['type' => 'bed_change']);

                return "Moved to bed {$bed->label}.";

            case 'accept_transfer':
                $transfer = $encounter->pendingTransfer;
                if (! $transfer) {
                    throw ValidationException::withMessages(['type' => 'There is no pending transfer for this patient.']);
                }
                if ($user->ward_id && (session('ward_id') ?? $user->ward_id) !== $transfer->to_ward_id) {
                    throw ValidationException::withMessages(['type' => 'Only a nurse working on '.$transfer->toWard->name.' can accept this transfer.']);
                }
                $this->movement->acceptTransfer($transfer, Bed::findOrFail($data['bed_id']), $user);

                return 'Patient accepted onto '.$transfer->toWard->name.'.';

            case 'off_ward':
                $dest = config('ward.off_ward_destinations')[$data['destination']];
                $encounter->update(['off_ward' => $dest]);
                $this->record($encounter, $user, EntryCategory::Movement, "Left ward for {$dest}".(! empty($data['escort']) ? " with {$data['escort']}" : ''),
                    ['destination' => $dest, 'escort' => $data['escort'] ?? null, 'notes' => $data['notes'] ?? null], ['type' => 'off_ward']);

                return "Patient marked as off the ward ({$dest}).";

            case 'returned':
                $was = $encounter->off_ward ?? 'off ward';
                $encounter->update(['off_ward' => null]);
                $this->record($encounter, $user, EntryCategory::Movement, "Returned to ward from {$was}",
                    ['from' => $was, 'notes' => $data['notes'] ?? null], ['type' => 'returned']);

                return 'Patient back on the ward.';
        }

        return 'Saved.';
    }
}
