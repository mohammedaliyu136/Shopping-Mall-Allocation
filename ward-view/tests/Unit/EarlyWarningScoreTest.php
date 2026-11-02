<?php

namespace Tests\Unit;

use App\Services\EarlyWarningScore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EarlyWarningScoreTest extends TestCase
{
    private function obs(array $override = []): array
    {
        return array_merge([
            'respiratory_rate' => 16, 'spo2' => 97, 'on_oxygen' => false, 'systolic_bp' => 120,
            'heart_rate' => 75, 'consciousness' => 'A', 'temperature' => 37.0,
        ], $override);
    }

    public function test_normal_observations_score_zero(): void
    {
        $r = (new EarlyWarningScore)->calculate($this->obs());
        $this->assertSame(0, $r['score']);
        $this->assertSame('low', $r['band']);
        $this->assertTrue($r['complete']);
    }

    public static function parameterBoundaries(): array
    {
        return [
            'RR 8' => [['respiratory_rate' => 8], 3], 'RR 11' => [['respiratory_rate' => 11], 1],
            'RR 21' => [['respiratory_rate' => 21], 2], 'RR 25' => [['respiratory_rate' => 25], 3],
            'SpO2 91' => [['spo2' => 91], 3], 'SpO2 93' => [['spo2' => 93], 2], 'SpO2 95' => [['spo2' => 95], 1],
            'oxygen' => [['on_oxygen' => true], 2],
            'SBP 90' => [['systolic_bp' => 90], 3], 'SBP 100' => [['systolic_bp' => 100], 2],
            'SBP 110' => [['systolic_bp' => 110], 1], 'SBP 220' => [['systolic_bp' => 220], 3],
            'HR 40' => [['heart_rate' => 40], 3], 'HR 50' => [['heart_rate' => 50], 1], 'HR 110' => [['heart_rate' => 110], 1],
            'HR 130' => [['heart_rate' => 130], 2], 'HR 131' => [['heart_rate' => 131], 3],
            'Confused' => [['consciousness' => 'C'], 3],
            'T 35.0' => [['temperature' => 35.0], 3], 'T 36.0' => [['temperature' => 36.0], 1],
            'T 38.1' => [['temperature' => 38.1], 1], 'T 39.1' => [['temperature' => 39.1], 2],
        ];
    }

    #[DataProvider('parameterBoundaries')]
    public function test_parameter_boundaries(array $override, int $expected): void
    {
        $this->assertSame($expected, (new EarlyWarningScore)->calculate($this->obs($override))['score']);
    }

    public function test_bands(): void
    {
        $news = new EarlyWarningScore;
        $this->assertSame('low-medium', $news->calculate($this->obs(['consciousness' => 'V']))['band']);
        $this->assertSame('medium', $news->calculate($this->obs(['respiratory_rate' => 22, 'spo2' => 93, 'heart_rate' => 95]))['band']);
        $this->assertSame('high', $news->calculate($this->obs(['respiratory_rate' => 26, 'spo2' => 90, 'on_oxygen' => true]))['band']);
        $this->assertFalse($news->calculate(['heart_rate' => 80])['complete']);
    }
}
