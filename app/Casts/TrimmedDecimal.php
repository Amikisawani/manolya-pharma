<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Decimal cast that keeps DB precision but drops trailing zeros in PHP/JSON
 * so UI never receives misleading values like "12.000" (read as 12 000 in FR).
 *
 * @implements CastsAttributes<string|null, string|float|int|null>
 */
final class TrimmedDecimal implements CastsAttributes
{
    public function __construct(private readonly int $scale = 3) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::format((string) $value, $this->scale);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new \InvalidArgumentException("Attribute [{$key}] must be numeric.");
        }

        return number_format((float) $value, $this->scale, '.', '');
    }

    public static function format(string|float|int $value, int $scale = 3): string
    {
        $normalized = number_format((float) $value, $scale, '.', '');
        $trimmed = rtrim(rtrim($normalized, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }
}
