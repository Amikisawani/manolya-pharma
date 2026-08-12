<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;

final readonly class PaymentIntent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Money $amount,
        public string $method,
        public ?string $provider = null,
        public ?string $reference = null,
        public array $metadata = [],
    ) {}
}
