<?php

namespace App\Infrastructure\Payments;

final readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerRef = null,
        public ?string $provider = null,
        public ?string $message = null,
    ) {}

    public static function ok(?string $providerRef = null, ?string $provider = null): self
    {
        return new self(true, $providerRef, $provider);
    }

    public static function failed(string $message, ?string $provider = null): self
    {
        return new self(false, null, $provider, $message);
    }
}
