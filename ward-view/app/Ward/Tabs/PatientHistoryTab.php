<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Models\Encounter;
use App\Models\WardConfig;

/** The patient's full file: everything done on the ward is recorded here. */
class PatientHistoryTab extends WardTab
{
    public function key(): string
    {
        return 'history';
    }

    public function label(): string
    {
        return 'Patient History';
    }

    public function description(): string
    {
        return 'The full file: notes, observations, investigations, medication, procedures and movements.';
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        $filter = request('category');
        $query = $encounter->entries()->with(['author', 'cosigner'])->latest('recorded_at')->latest('id');
        if ($filter && EntryCategory::tryFrom($filter)) {
            $query->where('category', $filter);
        }

        return [
            'entries' => $query->limit(200)->get(),
            'filter' => $filter,
            'categories' => $encounter->entries()->distinct()->pluck('category'),
        ];
    }
}
