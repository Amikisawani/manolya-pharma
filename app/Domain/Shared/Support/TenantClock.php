<?php

namespace App\Domain\Shared\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;

final class TenantClock
{
    public static function timezone(?Tenant $tenant): string
    {
        $timezone = $tenant?->timezone;

        return is_string($timezone) && $timezone !== '' ? $timezone : 'Africa/Kinshasa';
    }

    public static function now(?Tenant $tenant = null): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone($tenant));
    }

    public static function today(?Tenant $tenant = null): string
    {
        return self::now($tenant)->toDateString();
    }

    public static function format(?\DateTimeInterface $value, ?Tenant $tenant, string $format = 'd/m/Y H:i'): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse($value)
            ->timezone(self::timezone($tenant))
            ->format($format);
    }
}
