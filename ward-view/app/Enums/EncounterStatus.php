<?php

namespace App\Enums;

enum EncounterStatus: string
{
    /** A&E: arrived, waiting for triage or to be seen. */
    case Waiting = 'waiting';
    case Admitted = 'admitted';
    /** Doctor has started a discharge; the nurse checklist is now open. */
    case DischargePending = 'discharge_pending';
    case Discharged = 'discharged';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Admitted => 'Admitted',
            self::DischargePending => 'Discharge in progress',
            self::Discharged => 'Discharged',
        };
    }

    /** Statuses that keep a patient on the ward list. */
    public static function active(): array
    {
        return [self::Waiting->value, self::Admitted->value, self::DischargePending->value];
    }
}
