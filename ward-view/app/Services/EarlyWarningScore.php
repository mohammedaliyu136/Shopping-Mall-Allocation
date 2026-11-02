<?php

namespace App\Services;

/**
 * NEWS2 (Royal College of Physicians, 2017), SpO2 scale 1.
 * Returns the aggregate score, per-parameter scores and the clinical response band.
 */
class EarlyWarningScore
{
    public const PARAMETERS = ['respiratory_rate', 'spo2', 'on_oxygen', 'systolic_bp', 'heart_rate', 'consciousness', 'temperature'];

    public function calculate(array $obs): array
    {
        $parts = [];

        if (isset($obs['respiratory_rate'])) {
            $rr = (int) $obs['respiratory_rate'];
            $parts['respiratory_rate'] = match (true) {
                $rr <= 8 => 3, $rr <= 11 => 1, $rr <= 20 => 0, $rr <= 24 => 2, default => 3,
            };
        }

        if (isset($obs['spo2'])) {
            $s = (int) $obs['spo2'];
            $parts['spo2'] = match (true) {
                $s <= 91 => 3, $s <= 93 => 2, $s <= 95 => 1, default => 0,
            };
        }

        if (array_key_exists('on_oxygen', $obs) && $obs['on_oxygen'] !== null) {
            $parts['on_oxygen'] = filter_var($obs['on_oxygen'], FILTER_VALIDATE_BOOLEAN) ? 2 : 0;
        }

        if (isset($obs['systolic_bp'])) {
            $bp = (int) $obs['systolic_bp'];
            $parts['systolic_bp'] = match (true) {
                $bp <= 90 => 3, $bp <= 100 => 2, $bp <= 110 => 1, $bp <= 219 => 0, default => 3,
            };
        }

        if (isset($obs['heart_rate'])) {
            $hr = (int) $obs['heart_rate'];
            $parts['heart_rate'] = match (true) {
                $hr <= 40 => 3, $hr <= 50 => 1, $hr <= 90 => 0, $hr <= 110 => 1, $hr <= 130 => 2, default => 3,
            };
        }

        if (! empty($obs['consciousness'])) {
            $parts['consciousness'] = strtoupper($obs['consciousness']) === 'A' ? 0 : 3;
        }

        if (isset($obs['temperature'])) {
            $t = round((float) $obs['temperature'], 1);
            $parts['temperature'] = match (true) {
                $t <= 35.0 => 3, $t <= 36.0 => 1, $t <= 38.0 => 0, $t <= 39.0 => 1, default => 2,
            };
        }

        $total = array_sum($parts);
        $anyThree = in_array(3, $parts, true);

        return [
            'score' => $total,
            'parts' => $parts,
            'complete' => count($parts) === count(self::PARAMETERS),
            'band' => $this->band($total, $anyThree),
        ];
    }

    public function band(int $total, bool $anySingleThree = false): string
    {
        return match (true) {
            $total >= 7 => 'high',
            $total >= 5 => 'medium',
            $anySingleThree => 'low-medium',
            default => 'low',
        };
    }

    public function response(string $band): string
    {
        return match ($band) {
            'high' => 'Emergency response: alert the doctor now and consider critical care outreach.',
            'medium' => 'Urgent response: alert the doctor for an urgent review.',
            'low-medium' => 'A single parameter scored 3: ask the doctor to review.',
            default => 'Continue routine monitoring.',
        };
    }

    public function needsEscalation(string $band): bool
    {
        return $band !== 'low';
    }
}
