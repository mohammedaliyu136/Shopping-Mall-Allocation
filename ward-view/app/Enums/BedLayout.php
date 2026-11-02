<?php

namespace App\Enums;

enum BedLayout: string
{
    case Bays = 'bays';
    case Nightingale = 'nightingale';
    case SideRooms = 'side_rooms';
    case Pods = 'pods';
    case Cubicles = 'cubicles';

    public function label(): string
    {
        return match ($this) {
            self::Bays => 'Bays of 6 beds',
            self::Nightingale => 'Open (Nightingale) ward',
            self::SideRooms => 'Single side rooms',
            self::Pods => 'Critical care pods',
            self::Cubicles => 'A&E cubicles',
        };
    }

    /** How many beds share a group (bay/pod) when the ward is drawn. */
    public function groupSize(): int
    {
        return match ($this) {
            self::Bays => 6,
            self::Nightingale => 30,
            self::SideRooms => 1,
            self::Pods => 4,
            self::Cubicles => 8,
        };
    }

    public function groupName(): string
    {
        return match ($this) {
            self::Bays => 'Bay',
            self::Nightingale => 'Ward',
            self::SideRooms => 'Room',
            self::Pods => 'Pod',
            self::Cubicles => 'Zone',
        };
    }

    public function bedPrefix(): string
    {
        return match ($this) {
            self::SideRooms => 'SR',
            self::Cubicles => 'C',
            default => 'B',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
