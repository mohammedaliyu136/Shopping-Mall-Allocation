<?php

namespace App\Ward\Tabs;

use App\Enums\EntryCategory;
use App\Enums\ObsChartType;
use App\Models\Encounter;
use App\Models\WardConfig;

/** The observation chart. Its shape follows the ward's configured chart type. */
class ObservationsTab extends WardTab
{
    public function key(): string
    {
        return 'observations';
    }

    public function label(): string
    {
        return 'Obs Chart';
    }

    public function description(): string
    {
        return 'Vital signs over time with NEWS2, pain and fluid balance.';
    }

    public function data(Encounter $encounter, WardConfig $config): array
    {
        $hourly = $config->obs_chart_type === ObsChartType::Hourly;
        $obs = $encounter->entries()->where('category', EntryCategory::Observation->value)
            ->where('recorded_at', '>=', now()->subHours($hourly ? 24 : 72))
            ->orderBy('recorded_at')->get();

        $today = $obs->filter(fn ($o) => $o->recorded_at->isToday());

        return [
            'obs' => $obs->take(-($hourly ? 24 : 18)),
            'chartType' => $config->obs_chart_type,
            'frequency' => $encounter->obsFrequencyHours($config),
            'dueAt' => $encounter->obsDueAt($config),
            'fluidIn' => $today->sum(fn ($o) => $o->data('fluid_in_ml', 0)),
            'fluidOut' => $today->sum(fn ($o) => $o->data('fluid_out_ml', 0)),
        ];
    }
}
