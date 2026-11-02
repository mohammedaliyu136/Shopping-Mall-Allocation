<?php

namespace App\Enums;

/**
 * Every clinical record written from the ward view is a ClinicalEntry. The
 * category decides which tabs show it. Patient History shows them all.
 */
enum EntryCategory: string
{
    case Note = 'note';
    case Observation = 'observation';
    case Investigation = 'investigation';
    case Medication = 'medication';
    case Administration = 'administration';
    case Procedure = 'procedure';
    case Movement = 'movement';
    case Discharge = 'discharge';
    case Alert = 'alert';
    case Assessment = 'assessment';
    case Triage = 'triage';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Note',
            self::Observation => 'Observations',
            self::Investigation => 'Investigation',
            self::Medication => 'Medication',
            self::Administration => 'Administration',
            self::Procedure => 'Procedure',
            self::Movement => 'Movement',
            self::Discharge => 'Discharge',
            self::Alert => 'Alert',
            self::Assessment => 'Risk assessment',
            self::Triage => 'Triage',
        };
    }
}
