<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\WardType;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardConfig;
use App\Ward\Actions\ActionRegistry;
use App\Ward\Tabs\TabRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3: ward config is read on every ward and patient screen, so it is
 * cached and only refreshed when an admin saves a change (the version bumps
 * and the cache entry is replaced).
 */
class WardConfigRepository
{
    public function __construct(
        private ActionRegistry $actions,
        private TabRegistry $tabs,
        private BedManager $beds,
    ) {}

    public static function cacheKey(int $wardId): string
    {
        return "ward-config:{$wardId}";
    }

    public function for(Ward|int $ward): WardConfig
    {
        $wardId = $ward instanceof Ward ? $ward->id : $ward;

        $attributes = Cache::rememberForever(self::cacheKey($wardId), function () use ($ward, $wardId) {
            $config = WardConfig::where('ward_id', $wardId)->first()
                ?? $this->createDefault($ward instanceof Ward ? $ward : Ward::findOrFail($wardId));

            return $config->getAttributes();
        });

        return (new WardConfig)->newFromBuilder($attributes);
    }

    public function createDefault(Ward $ward, ?WardType $type = null, ?User $by = null, string $event = 'created'): WardConfig
    {
        $type ??= WardConfigDefaults::inferType($ward->name, $ward->code);

        return DB::transaction(function () use ($ward, $type, $by, $event) {
            $config = $ward->config()->create(WardConfigDefaults::for($type) + ['version' => 1]);
            $this->beds->sync($ward, $config);
            $this->log($ward, $config, null, $by, $event);
            Cache::forget(self::cacheKey($ward->id));

            return $config;
        });
    }

    /** Save an admin change: sanitise keys, bump the version, log it and refresh the cache. */
    public function save(Ward $ward, array $input, User $by): WardConfig
    {
        return DB::transaction(function () use ($ward, $input, $by) {
            $config = $ward->config()->lockForUpdate()->first() ?? $this->createDefault($ward, null, $by);
            $before = $config->snapshot();

            $type = WardType::from($input['ward_type']);

            // "Apply defaults" (ticked automatically when the type changes on the
            // form) resets buttons, tabs and the obs chart to the type's defaults.
            $source = ! empty($input['apply_defaults']) ? WardConfigDefaults::for($type) : $input;

            $config->fill([
                'ward_type' => $type,
                'bed_layout' => $input['bed_layout'],
                'bed_count' => (int) $input['bed_count'],
                'obs_chart_type' => $source['obs_chart_type'] instanceof \BackedEnum ? $source['obs_chart_type']->value : $source['obs_chart_type'],
                'obs_frequency_hours' => (int) $source['obs_frequency_hours'],
                'rollout_stage' => $input['rollout_stage'] ?? $config->rollout_stage->value,
                'enabled_tabs' => $this->tabs->sanitise($source['enabled_tabs'] ?? [], $type),
                'doctor_actions' => $this->actions->sanitise($source['doctor_actions'] ?? [], UserRole::Doctor, $type),
                'nurse_actions' => $this->actions->sanitise($source['nurse_actions'] ?? [], UserRole::Nurse, $type),
            ]);

            if ($config->snapshot() === $before) {
                return $config;
            }

            $config->version++;
            $config->save();
            $this->beds->sync($ward, $config);
            $this->log($ward, $config, $before, $by, 'updated');
            Cache::forget(self::cacheKey($ward->id));

            return $config;
        });
    }

    private function log(Ward $ward, WardConfig $config, ?array $before, ?User $by, string $event): void
    {
        $ward->configChanges()->create([
            'user_id' => $by?->id,
            'user_name' => $by?->name ?? 'System',
            'event' => $event,
            'before' => $before,
            'after' => $config->snapshot(),
            'version' => $config->version,
        ]);
    }

    /** What the client caches: the config plus the resolved buttons and tabs. */
    public function clientPayload(Ward $ward, WardConfig $config): array
    {
        return [
            'ward' => ['id' => $ward->id, 'name' => $ward->name, 'code' => $ward->code],
            'version' => $config->version,
            'config' => $config->snapshot(),
            'buttons' => [
                'doctor' => $this->actions->enabledFor($config, UserRole::Doctor)->map->toArray()->values(),
                'nurse' => $this->actions->enabledFor($config, UserRole::Nurse)->map->toArray()->values(),
            ],
            'tabs' => $this->tabs->enabledFor($config)->map(fn ($t) => ['key' => $t->key(), 'label' => $t->label()])->values(),
        ];
    }
}
