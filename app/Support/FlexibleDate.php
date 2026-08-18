<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class FlexibleDate
{
    /**
     * Normalise une date Excel / formulaire en Y-m-d, ou null si illisible.
     */
    public static function toDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $n = (float) $value;
            // Numéro de série Excel (jours depuis 1899-12-30), typique 1954–2199.
            if ($n > 20000 && $n < 120000) {
                $unix = (int) round(($n - 25569) * 86400);

                return gmdate('Y-m-d', $unix);
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'] as $fmt) {
            $dt = DateTimeImmutable::createFromFormat('!'.$fmt, $s);
            if (! $dt instanceof DateTimeImmutable) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();
            if ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)) {
                return $dt->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($s)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
