<?php

namespace App\Infrastructure\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generic HTTP SMS adapter used by Orange / Airtel Congo integrations.
 * Expects JSON API: POST {url} with bearer token → { to, message, sender?, from? }.
 */
abstract class HttpSmsGateway implements SmsGateway
{
    abstract protected function providerKey(): string;

    public function send(string $to, string $message, array $context = []): SmsResult
    {
        $cfg = config('services.sms.'.$this->providerKey(), []);
        $url = $cfg['url'] ?? null;
        $token = $cfg['token'] ?? null;
        $sender = $cfg['sender'] ?? config('app.name');

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            Log::warning("SMS {$this->providerKey()}: credentials missing, falling back to log", [
                'to' => $to,
            ]);

            return app(LogSmsGateway::class)->send($to, $message, array_merge($context, [
                'fallback_from' => $this->providerKey(),
            ]));
        }

        $normalized = $this->normalizePhone($to);

        try {
            $response = Http::timeout((int) ($cfg['timeout'] ?? 30))
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'to' => $normalized,
                    'message' => $message,
                    'sender' => $sender,
                    'from' => $sender,
                    'context' => $context,
                ]);

            if (! $response->successful()) {
                return SmsResult::failed(
                    $this->providerKey(),
                    'HTTP '.$response->status().' '.$response->body()
                );
            }

            $ref = (string) ($response->json('id') ?? $response->json('message_id') ?? 'SMS-'.Str::upper(Str::random(8)));

            return SmsResult::ok($this->providerKey(), $ref);
        } catch (\Throwable $e) {
            Log::error("SMS {$this->providerKey()} failed", ['error' => $e->getMessage(), 'to' => $normalized]);

            return SmsResult::failed($this->providerKey(), $e->getMessage());
        }
    }

    protected function normalizePhone(string $to): string
    {
        $digits = preg_replace('/\D+/', '', $to) ?? '';
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '243'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '243') && strlen($digits) === 9) {
            return '243'.$digits;
        }

        return $digits;
    }
}
