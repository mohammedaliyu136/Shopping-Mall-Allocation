<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Models\WardConfig;

class MedicationTab extends WardTab
{
    public function key(): string
    {
        return 'medication';
    }

    public function label(): string
    {
        return 'Medication';
    }

    public function description(): string
    {
        return 'The drug chart: every prescription, fluid and transfusion with its administrations.';
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        $meds = $encounter->entries()
            ->where('category', EntryCategory::Medication->value)
            ->whereIn('type', ['new', 'modify', 'iv_fluids', 'transfusion'])
            ->with(['author', 'children' => fn ($q) => $q->where('category', EntryCategory::Administration->value)->with('author')->latest('recorded_at')])
            ->latest('recorded_at')->get();

        return [
            'active' => $meds->where('status', 'active'),
            'inactive' => $meds->where('status', '!=', 'active'),
        ];
    }
}
