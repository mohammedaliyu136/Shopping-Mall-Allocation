<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Models\WardConfig;

class InvestigationsTab extends WardTab
{
    public function key(): string
    {
        return 'investigations';
    }

    public function label(): string
    {
        return 'Investigations';
    }

    public function description(): string
    {
        return 'All lab, radiology and scan requests with their status and results.';
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        $all = $encounter->entries()
            ->where('category', EntryCategory::Investigation->value)
            ->where('type', '!=', 'sample')
            ->with('author')->latest('recorded_at')->get();

        return ['groups' => $all->groupBy(fn ($e) => $e->data('kind', 'Other'))];
    }
}
