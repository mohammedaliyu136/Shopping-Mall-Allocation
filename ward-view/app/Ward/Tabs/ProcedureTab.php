<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Models\WardConfig;

class ProcedureTab extends WardTab
{
    public function key(): string
    {
        return 'procedure';
    }

    public function label(): string
    {
        return 'Procedure';
    }

    public function description(): string
    {
        return 'All procedures and theatre bookings for the patient.';
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        return [
            'procedures' => $encounter->entries()->where('category', EntryCategory::Procedure->value)
                ->with('author')->latest('recorded_at')->get(),
        ];
    }
}
