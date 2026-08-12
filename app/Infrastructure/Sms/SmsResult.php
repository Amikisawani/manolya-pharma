<?php

namespace App\Infrastructure\Sms;

final readonly class SmsResult
{
    public function __construct(
        public bool $successful,
        public string $provider,
        public ?string $providerRef = null,
        public ?string $message = null,
    ) {}

    public static function ok(string $provider, ?string $providerRef = null): self
    {
        return new self(true, $provider, $providerRef);
    }

    public static function failed(string $provider, string $message): self
    {
        return new self(false, $provider, null, $message);
    }
}
