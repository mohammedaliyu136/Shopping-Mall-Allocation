<?php

namespace App\Console\Commands;

use App\Enums\RolloutStage;
use App\Enums\WardType;
use App\Models\Ward;
use App\Services\WardConfigDefaults;
use App\Services\WardConfigRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 6: map existing wards to a type and its default config. Wards that
 * already have a config are left alone. New configs start at "not started"
 * unless named as pilots, so wards move to the new view one at a time.
 */
class MapWardTypes extends Command
{
    protected $signature = 'wards:map-types
        {--dry-run : Show the mapping without saving}
        {--pilot=* : Ward codes to put into the pilot}
        {--live : Make every newly mapped ward live straight away}
        {--type=* : Override the inferred type, as CODE=general|critical_care|emergency}';

    protected $description = 'Map existing wards to a ward type and create their default configuration';

    public function handle(WardConfigRepository $configs): int
    {
        $overrides = collect($this->option('type'))->mapWithKeys(function ($pair) {
            [$code, $type] = array_pad(explode('=', $pair, 2), 2, null);

            return [strtoupper($code) => WardType::tryFrom((string) $type)];
        })->filter();
        $pilots = array_map('strtoupper', $this->option('pilot'));

        $rows = [];
        foreach (Ward::with('config')->orderBy('name')->get() as $ward) {
            if ($ward->config) {
                $rows[] = [$ward->code, $ward->name, $ward->config->ward_type->label(), $ward->config->rollout_stage->label(), 'already configured'];

                continue;
            }

            $type = $overrides->get(strtoupper($ward->code)) ?? WardConfigDefaults::inferType($ward->name, $ward->code);
            $stage = in_array(strtoupper($ward->code), $pilots, true) ? RolloutStage::Pilot
                : ($this->option('live') ? RolloutStage::Live : RolloutStage::NotStarted);

            if (! $this->option('dry-run')) {
                $config = $configs->createDefault($ward, $type, null, 'migrated');
                $config->update(['rollout_stage' => $stage]);
                Cache::forget(WardConfigRepository::cacheKey($ward->id));
            }
            $rows[] = [$ward->code, $ward->name, $type->label(), $stage->label(), $this->option('dry-run') ? 'would map' : 'mapped'];
        }

        $this->table(['Code', 'Ward', 'Type', 'Rollout', 'Result'], $rows);
        $this->info($this->option('dry-run') ? 'Dry run: nothing saved.' : 'Done. Review the types on the admin Ward setup screen.');

        return self::SUCCESS;
    }
}
