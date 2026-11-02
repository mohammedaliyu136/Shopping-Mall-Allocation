<?php

namespace App\Enums;

enum ObsChartType: string
{
    case Standard = 'standard';
    case Hourly = 'hourly';
    case Triage = 'triage';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard NEWS2 chart',
            self::Hourly => 'Hourly critical care chart',
            self::Triage => 'Triage observations (A&E)',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
