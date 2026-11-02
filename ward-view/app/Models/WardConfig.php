<?php

namespace App\Models;

use App\Enums\BedLayout;
use App\Enums\ObsChartType;
use App\Enums\RolloutStage;
use App\Enums\UserRole;
use App\Enums\WardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the master template controls per ward: action buttons per role, bed
 * layout and count, observation chart type and frequency, and which tabs show.
 * The ward screen only ever reads this; it never builds layouts at runtime.
 */
class WardConfig extends Model
{
    protected $fillable = [
        'ward_type', 'bed_layout', 'bed_count', 'enabled_tabs', 'doctor_actions',
        'nurse_actions', 'obs_chart_type', 'obs_frequency_hours', 'rollout_stage', 'version',
    ];

    protected function casts(): array
    {
        return [
            'ward_type' => WardType::class,
            'bed_layout' => BedLayout::class,
            'obs_chart_type' => ObsChartType::class,
            'rollout_stage' => RolloutStage::class,
            'enabled_tabs' => 'array',
            'doctor_actions' => 'array',
            'nurse_actions' => 'array',
            'bed_count' => 'integer',
            'obs_frequency_hours' => 'integer',
            'version' => 'integer',
        ];
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** @return list<string> */
    public function actionsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::Doctor => $this->doctor_actions ?? [],
            UserRole::Nurse => $this->nurse_actions ?? [],
            default => [],
        };
    }

    public function hasTab(string $key): bool
    {
        return in_array($key, $this->enabled_tabs ?? [], true);
    }

    /** The comparable shape used for the change log and the client cache. */
    public function snapshot(): array
    {
        return [
            'ward_type' => $this->ward_type->value,
            'bed_layout' => $this->bed_layout->value,
            'bed_count' => $this->bed_count,
            'enabled_tabs' => array_values($this->enabled_tabs ?? []),
            'doctor_actions' => array_values($this->doctor_actions ?? []),
            'nurse_actions' => array_values($this->nurse_actions ?? []),
            'obs_chart_type' => $this->obs_chart_type->value,
            'obs_frequency_hours' => $this->obs_frequency_hours,
            'rollout_stage' => $this->rollout_stage->value,
        ];
    }
}
