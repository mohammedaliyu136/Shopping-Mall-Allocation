<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Doctor = 'doctor';
    case Nurse = 'nurse';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Roles that have a clinical action button bar. */
    public static function clinical(): array
    {
        return [self::Doctor, self::Nurse];
    }
}
