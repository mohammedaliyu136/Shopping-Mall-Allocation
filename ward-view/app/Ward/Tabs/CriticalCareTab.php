<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Enums\WardType;
use App\Models\Encounter;
use App\Models\WardConfig;
use App\Ward\Actions\Nurse\RecordObservations;

/** Critical care only: ventilator settings and running infusions over the last 24 hours. */
class CriticalCareTab extends WardTab
{
    public function key(): string
    {
        return 'critical_care';
    }

    public function label(): string
    {
        return 'Vent & Infusions';
    }

    public function description(): string
    {
        return 'Ventilator settings and infusion rates, hour by hour.';
    }

    public function wardTypes(): array
    {
        return [WardType::CriticalCare];
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        $obs = $encounter->entries()->where('category', EntryCategory::Observation->value)
            ->where('recorded_at', '>=', now()->subDay())->orderBy('recorded_at')->get();

        return [
            'ventilation' => $obs->filter(fn ($o) => $o->data('ventilator'))->values(),
            'infusions' => RecordObservations::infusions($encounter),
            'rates' => $obs->flatMap(fn ($o) => collect($o->data('infusions', []))->map(fn ($i) => $i + ['at' => $o->recorded_at]))
                ->groupBy('prescription_id'),
        ];
    }
}
