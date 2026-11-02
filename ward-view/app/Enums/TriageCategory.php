<?php

namespace App\Enums;

/** Manchester-style five-level triage scale. */
enum TriageCategory: int
{
    case Immediate = 1;
    case VeryUrgent = 2;
    case Urgent = 3;
    case Standard = 4;
    case NonUrgent = 5;

    public function label(): string
    {
        return match ($this) {
            self::Immediate => '1 · Immediate',
            self::VeryUrgent => '2 · Very urgent',
            self::Urgent => '3 · Urgent',
            self::Standard => '4 · Standard',
            self::NonUrgent => '5 · Non-urgent',
        };
    }

    public function colour(): string
    {
        return match ($this) {
            self::Immediate => 'red',
            self::VeryUrgent => 'orange',
            self::Urgent => 'yellow',
            self::Standard => 'green',
            self::NonUrgent => 'blue',
        };
    }

    /** Target time to be seen by a clinician, in minutes. */
    public function targetMinutes(): int
    {
        return match ($this) {
            self::Immediate => 0,
            self::VeryUrgent => 10,
            self::Urgent => 60,
            self::Standard => 120,
            self::NonUrgent => 240,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
