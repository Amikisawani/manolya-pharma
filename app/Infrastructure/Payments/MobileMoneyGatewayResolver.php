<?php

namespace App\Infrastructure\Payments;

final class MobileMoneyGatewayResolver
{
    public function __construct(
        private readonly MobileMoneyStubGateway $stub,
        private readonly OrangeMoneyGateway $orange,
        private readonly AirtelMoneyGateway $airtel,
        private readonly MtnMomoGateway $mtn,
    ) {}

    public function resolve(?string $provider): PaymentGateway
    {
        $key = strtolower((string) ($provider ?: config('services.momo.default', 'stub')));

        return match ($key) {
            'orange', 'orange_money', 'om' => $this->orange,
            'airtel', 'airtel_money' => $this->airtel,
            'mtn', 'mtn_momo', 'momo' => $this->mtn,
            default => $this->stub,
        };
    }
}
