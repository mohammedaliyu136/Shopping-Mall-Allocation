<?php

namespace App\Support;

/**
 * Exact decimal money arithmetic.
 *
 * Amounts are handled as integer minor units (kobo, cents) so that adding,
 * comparing and apportioning invoice lines and payments never drifts the way
 * binary floating point does. Values cross the boundary as plain decimal
 * strings with two places, which is what the database columns store.
 *
 * This deliberately avoids ext-bcmath so the application runs on a stock PHP
 * build with no extra extensions.
 */
final class Money
{
    public const SCALE = 2;

    private const FACTOR = 100;

    public static function add(int|float|string|null $a, int|float|string|null $b): string
    {
        return self::fromMinor(self::toMinor($a) + self::toMinor($b));
    }

    public static function subtract(int|float|string|null $a, int|float|string|null $b): string
    {
        return self::fromMinor(self::toMinor($a) - self::toMinor($b));
    }

    /** Multiplies an amount by a quantity, which may itself be fractional. */
    public static function multiply(int|float|string|null $amount, int|float|string|null $quantity): string
    {
        return self::fromMinor((int) round(self::toMinor($amount) * self::toMinor($quantity) / self::FACTOR));
    }

    public static function divide(int|float|string|null $amount, int|float|string $divisor): string
    {
        $divisor = (float) $divisor;

        if ($divisor == 0.0) {
            throw new \InvalidArgumentException('Cannot divide an amount by zero.');
        }

        return self::fromMinor((int) round(self::toMinor($amount) / $divisor));
    }

    /** Returns -1, 0 or 1, matching the spaceship operator. */
    public static function compare(int|float|string|null $a, int|float|string|null $b): int
    {
        return self::toMinor($a) <=> self::toMinor($b);
    }

    public static function isPositive(int|float|string|null $amount): bool
    {
        return self::toMinor($amount) > 0;
    }

    /** True when the amount is zero or negative - i.e. nothing is owed. */
    public static function isSettled(int|float|string|null $amount): bool
    {
        return self::toMinor($amount) <= 0;
    }

    /** Normalises any numeric input to a two-place decimal string. */
    public static function of(int|float|string|null $amount): string
    {
        return self::fromMinor(self::toMinor($amount));
    }

    /** Human-readable, with thousands separators. */
    public static function format(int|float|string|null $amount, string $symbol = ''): string
    {
        return $symbol.number_format(self::toMinor($amount) / self::FACTOR, self::SCALE);
    }

    /** Parses a decimal value into minor units, rounding half up. */
    public static function toMinor(int|float|string|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value * self::FACTOR;
        }

        $string = trim((string) $value);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $string, $matches)) {
            // Not a plain decimal (scientific notation, for instance): fall back
            // to a float conversion, which is still correct to the cent for the
            // magnitudes this system deals in.
            return (int) round(((float) $string) * self::FACTOR);
        }

        $sign = $matches[1] === '-' ? -1 : 1;
        $whole = $matches[2] === '' ? '0' : $matches[2];
        $fraction = $matches[3] ?? '';

        $minor = (int) $whole * self::FACTOR + (int) substr($fraction.'00', 0, self::SCALE);

        // Round half up on anything beyond two decimal places.
        if ((int) substr($fraction.'000', self::SCALE, 1) >= 5) {
            $minor++;
        }

        return $sign * $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($minor, self::FACTOR), $minor % self::FACTOR);
    }
}
