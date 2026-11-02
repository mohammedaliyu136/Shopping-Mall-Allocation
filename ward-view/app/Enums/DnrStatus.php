<?php

namespace App\Enums;

enum DnrStatus: string
{
    case NotRecorded = 'not_recorded';
    case FullEscalation = 'full_escalation';
    case Dnacpr = 'dnacpr';

    public function label(): string
    {
        return match ($this) {
            self::NotRecorded => 'Resus status not recorded',
            self::FullEscalation => 'For full resuscitation',
            self::Dnacpr => 'DNACPR',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
