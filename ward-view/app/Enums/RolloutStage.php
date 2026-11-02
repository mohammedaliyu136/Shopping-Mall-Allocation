<?php

namespace App\Enums;

/** Phase 6: wards are moved onto the configurable view one at a time. */
enum RolloutStage: string
{
    case NotStarted = 'not_started';
    case Pilot = 'pilot';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Pilot => 'Pilot',
            self::Live => 'Live',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
