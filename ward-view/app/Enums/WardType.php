<?php

namespace App\Enums;

/**
 * The three fixed ward types. Every ward is built from the same master
 * template, but belongs to exactly one of these types. Types are fixed in
 * code; wards only choose between them.
 */
enum WardType: string
{
    case General = 'general';
    case CriticalCare = 'critical_care';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General ward',
            self::CriticalCare => 'Critical care (ICU / HDU)',
            self::Emergency => 'Emergency (A&E)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::General => 'Medical, surgical, paediatrics, maternity and similar wards.',
            self::CriticalCare => 'Adds hourly observation charts, ventilator and infusion tracking, and a 1:1 nursing view.',
            self::Emergency => 'Built around triage, waiting time and disposition. Patients are not yet admitted.',
        };
    }

    /** Patients on an emergency "ward" are attendances, not admissions. */
    public function admitsPatients(): bool
    {
        return $this !== self::Emergency;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
