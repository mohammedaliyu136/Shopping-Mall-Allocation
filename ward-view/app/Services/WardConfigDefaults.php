<?php

namespace App\Services;

use App\Enums\BedLayout;
use App\Enums\ObsChartType;
use App\Enums\RolloutStage;
use App\Enums\WardType;

/** Defaults per ward type, so a new ward works the moment it is created. */
class WardConfigDefaults
{
    public static function for(WardType $type): array
    {
        return match ($type) {
            WardType::General => [
                'ward_type' => $type,
                'bed_layout' => BedLayout::Bays,
                'bed_count' => 24,
                'enabled_tabs' => ['history', 'investigations', 'medication', 'procedure', 'observations'],
                'doctor_actions' => ['ward_round_note', 'prescribe', 'request', 'procedure', 'refer_consult_transfer', 'discharge'],
                'nurse_actions' => ['record_observations', 'administer_medication', 'nursing_note', 'collect_sample', 'alert_doctor', 'move_patient'],
                'obs_chart_type' => ObsChartType::Standard,
                'obs_frequency_hours' => 4,
                'rollout_stage' => RolloutStage::Live,
            ],
            WardType::CriticalCare => [
                'ward_type' => $type,
                'bed_layout' => BedLayout::Pods,
                'bed_count' => 8,
                'enabled_tabs' => ['history', 'investigations', 'medication', 'procedure', 'observations', 'critical_care'],
                'doctor_actions' => ['ward_round_note', 'prescribe', 'request', 'procedure', 'refer_consult_transfer', 'discharge'],
                'nurse_actions' => ['record_observations', 'administer_medication', 'nursing_note', 'collect_sample', 'alert_doctor', 'move_patient'],
                'obs_chart_type' => ObsChartType::Hourly,
                'obs_frequency_hours' => 1,
                'rollout_stage' => RolloutStage::Live,
            ],
            WardType::Emergency => [
                'ward_type' => $type,
                'bed_layout' => BedLayout::Cubicles,
                'bed_count' => 16,
                'enabled_tabs' => ['history', 'investigations', 'medication', 'observations'],
                'doctor_actions' => ['ward_round_note', 'prescribe', 'request', 'procedure', 'refer_consult_transfer', 'disposition'],
                'nurse_actions' => ['triage', 'record_observations', 'administer_medication', 'nursing_note', 'collect_sample', 'alert_doctor'],
                'obs_chart_type' => ObsChartType::Triage,
                'obs_frequency_hours' => 1,
                'rollout_stage' => RolloutStage::Live,
            ],
        };
    }

    /** Phase 6: guess a ward's type from its name or code when migrating. */
    public static function inferType(string $name, string $code = ''): WardType
    {
        $haystack = strtolower($name.' '.$code);

        return match (true) {
            (bool) preg_match('/\b(a ?& ?e|a and e|emergency|casualty|ed|aed)\b/', $haystack) => WardType::Emergency,
            (bool) preg_match('/\b(icu|itu|hdu|ccu|nicu|picu|critical|intensive|high dependency)\b/', $haystack) => WardType::CriticalCare,
            default => WardType::General,
        };
    }
}
