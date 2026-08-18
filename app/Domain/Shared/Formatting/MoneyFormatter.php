<?php

namespace App\Domain\Shared\Formatting;

/**
 * Formats amounts for POS receipts and pharmacy UI (Franc congolais).
 */
final class MoneyFormatter
{
    public function format(string|int|float $amount, string $symbol = 'Fc'): string
    {
        $normalized = $this->normalize($amount);
        $decimals = str_ends_with($normalized, '.00') ? 0 : 2;
        $formatted = number_format((float) $normalized, $decimals, ',', ' ');

        return $formatted.' '.$symbol;
    }

    public function formatQuantity(string|int|float $quantity): string
    {
        $normalized = number_format((float) $quantity, 3, '.', '');
        $normalized = rtrim(rtrim($normalized, '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }

    public function normalize(string|int|float $amount): string
    {
        if (is_int($amount) || is_float($amount)) {
            return number_format((float) $amount, 2, '.', '');
        }

        $amount = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($amount));
        $amount = str_replace(',', '.', $amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return '0.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }
}
