<?php

namespace App\Services;

use InvalidArgumentException;
use OverflowException;

class MoneyAmount
{
    public static function toCents(string|int $amount): int
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^(?<whole>\d+)(?:\.(?<fraction>\d{1,2}))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException('Money amounts must be non-negative decimal strings with up to two decimal places.');
        }

        $whole = ltrim($matches['whole'], '0');
        $whole = $whole === '' ? '0' : $whole;

        if (strlen($whole) > 16) {
            throw new OverflowException('The money amount is too large to represent in cents.');
        }

        $wholeCents = (int) $whole;
        if ($wholeCents > intdiv(PHP_INT_MAX - 99, 100)) {
            throw new OverflowException('The money amount is too large to represent in cents.');
        }

        $fraction = str_pad($matches['fraction'] ?? '', 2, '0');

        return ($wholeCents * 100) + (int) $fraction;
    }

    public static function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new OverflowException('The money total is too large to represent in cents.');
        }

        if ($right < 0 && $left < PHP_INT_MIN - $right) {
            throw new OverflowException('The money total is too large to represent in cents.');
        }

        return $left + $right;
    }

    public static function asDecimal(int $cents): string
    {
        $negative = $cents < 0;
        $absoluteCents = abs($cents);

        return ($negative ? '-' : '')
            .intdiv($absoluteCents, 100)
            .'.'
            .str_pad((string) ($absoluteCents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        $decimal = self::asDecimal($cents);
        [$whole, $fraction] = explode('.', ltrim($decimal, '-'));
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return ($cents < 0 ? '-' : '').$whole.'.'.$fraction;
    }
}
