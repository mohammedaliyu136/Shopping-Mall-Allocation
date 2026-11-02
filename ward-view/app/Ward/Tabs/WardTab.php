<?php

namespace App\Ward\Tabs;

use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\WardConfig;

/** One tab on the patient view, registered under a fixed key. */
abstract class WardTab
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /** Data the tab's Blade view needs. Loaded only when the tab is opened. */
    abstract public function data(Encounter $encounter, WardConfig $config): array;

    public function wardTypes(): array
    {
        return WardType::cases();
    }

    public function supports(WardType $type): bool
    {
        return in_array($type, $this->wardTypes(), true);
    }

    public function view(): string
    {
        return 'tabs.'.$this->key();
    }
}
