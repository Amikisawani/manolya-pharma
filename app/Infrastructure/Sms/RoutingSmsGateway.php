<?php

namespace App\Infrastructure\Sms;

/**
 * Routes SMS by driver config or Congo mobile prefix heuristics.
 */
final class RoutingSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly LogSmsGateway $log,
        private readonly OrangeSmsGateway $orange,
        private readonly AirtelSmsGateway $airtel,
    ) {}

    public function send(string $to, string $message, array $context = []): SmsResult
    {
        return $this->resolve($to)->send($to, $message, $context);
    }

    public function resolve(string $to): SmsGateway
    {
        $driver = strtolower((string) config('services.sms.driver', 'log'));

        return match ($driver) {
            'orange' => $this->orange,
            'airtel' => $this->airtel,
            'auto' => $this->resolveByPrefix($to),
            default => $this->log,
        };
    }

    private function resolveByPrefix(string $to): SmsGateway
    {
        $digits = preg_replace('/\D+/', '', $to) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '243'.substr($digits, 1);
        }

        $national = str_starts_with($digits, '243') ? substr($digits, 3) : $digits;
        $prefix = substr($national, 0, 2);

        $orangePrefixes = config('services.sms.orange.prefixes', ['80', '81', '84', '85', '89']);
        $airtelPrefixes = config('services.sms.airtel.prefixes', ['97', '98', '99']);

        if (in_array($prefix, $orangePrefixes, true)) {
            return $this->orange;
        }
        if (in_array($prefix, $airtelPrefixes, true)) {
            return $this->airtel;
        }

        return match (config('services.sms.auto_fallback', 'orange')) {
            'airtel' => $this->airtel,
            'log' => $this->log,
            default => $this->orange,
        };
    }
}
