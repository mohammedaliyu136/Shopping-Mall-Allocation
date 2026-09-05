<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Money is the arithmetic every balance in the system depends on, so it is
 * tested directly rather than only through the billing services.
 */
class MoneyTest extends TestCase
{
    #[Test]
    public function it_adds_and_subtracts_without_binary_drift(): void
    {
        // The classic float trap: 0.1 + 0.2 !== 0.3 in binary floating point.
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('0.00', Money::subtract(Money::add('0.10', '0.20'), '0.30'));

        $this->assertSame('1500000.00', Money::add(Money::add('1200000', '240000'), '60000'));
        $this->assertSame('-25.50', Money::subtract('10.00', '35.50'));
    }

    #[Test]
    public function it_multiplies_by_fractional_quantities(): void
    {
        $this->assertSame('300.00', Money::multiply('100.00', '3'));
        $this->assertSame('150.00', Money::multiply('100.00', '1.5'));
        $this->assertSame('0.33', Money::multiply('1.00', '0.333'));
    }

    #[Test]
    public function it_divides_and_rounds_half_up(): void
    {
        $this->assertSame('50.00', Money::divide('100.00', 2));
        $this->assertSame('33.33', Money::divide('100.00', 3));
        $this->assertSame('0.01', Money::divide('0.01', 1));
    }

    #[Test]
    public function it_rejects_division_by_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::divide('100.00', 0);
    }

    #[Test]
    public function it_compares_amounts(): void
    {
        $this->assertSame(0, Money::compare('10.00', '10'));
        $this->assertSame(1, Money::compare('10.01', '10.00'));
        $this->assertSame(-1, Money::compare('9.99', '10.00'));

        // Values differing beyond two places compare as equal, since a balance
        // is only ever held to the cent.
        $this->assertSame(0, Money::compare('10.001', '10.00'));
    }

    #[Test]
    public function it_reports_whether_a_balance_is_settled(): void
    {
        $this->assertTrue(Money::isSettled('0'));
        $this->assertTrue(Money::isSettled('0.00'));
        $this->assertTrue(Money::isSettled('-5.00'));
        $this->assertTrue(Money::isSettled(null));
        $this->assertFalse(Money::isSettled('0.01'));

        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
    }

    #[DataProvider('normalisationCases')]
    #[Test]
    public function it_normalises_input_to_two_places(int|float|string|null $input, string $expected): void
    {
        $this->assertSame($expected, Money::of($input));
    }

    /** @return array<string, array{0: int|float|string|null, 1: string}> */
    public static function normalisationCases(): array
    {
        return [
            'null' => [null, '0.00'],
            'empty string' => ['', '0.00'],
            'integer' => [1500, '1500.00'],
            'float' => [12.5, '12.50'],
            'trailing zeros' => ['10.500', '10.50'],
            'rounds half up' => ['10.005', '10.01'],
            'rounds down' => ['10.004', '10.00'],
            'no decimal point' => ['1500000', '1500000.00'],
            'leading plus' => ['+7.25', '7.25'],
            'negative' => ['-7.25', '-7.25'],
            'negative rounding' => ['-0.005', '-0.01'],
            'bare fraction' => ['.5', '0.50'],
        ];
    }

    #[Test]
    public function it_formats_with_separators_and_a_symbol(): void
    {
        $this->assertSame('1,500,000.00', Money::format('1500000'));
        $this->assertSame('₦1,200.50', Money::format('1200.5', '₦'));
    }
}
