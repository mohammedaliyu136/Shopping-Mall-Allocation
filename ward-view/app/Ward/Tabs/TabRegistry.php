<?php

namespace App\Ward\Tabs;

use App\Enums\WardType;
use App\Models\WardConfig;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class TabRegistry
{
    /** @var array<string, class-string<WardTab>> */
    public const TABS = [
        'history' => PatientHistoryTab::class,
        'investigations' => InvestigationsTab::class,
        'medication' => MedicationTab::class,
        'procedure' => ProcedureTab::class,
        'observations' => ObservationsTab::class,
        'critical_care' => CriticalCareTab::class,
    ];

    private array $instances = [];

    public function find(string $key): WardTab
    {
        if (! isset(self::TABS[$key])) {
            throw new InvalidArgumentException("Unknown ward tab [{$key}].");
        }

        return $this->instances[$key] ??= app(self::TABS[$key]);
    }

    public function all(): Collection
    {
        return collect(array_keys(self::TABS))->mapWithKeys(fn ($k) => [$k => $this->find($k)]);
    }

    public function available(WardType $type): Collection
    {
        return $this->all()->filter(fn (WardTab $t) => $t->supports($type));
    }

    public function enabledFor(WardConfig $config): Collection
    {
        return $this->available($config->ward_type)
            ->filter(fn (WardTab $t) => $config->hasTab($t->key()));
    }

    public function sanitise(array $keys, WardType $type): array
    {
        return array_values(array_filter($this->available($type)->keys()->all(), fn ($k) => in_array($k, $keys, true)));
    }
}
