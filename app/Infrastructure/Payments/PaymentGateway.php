<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;

interface PaymentGateway
{
    public function charge(PaymentIntent $intent): PaymentResult;

    public function refund(string $providerRef, Money $amount): PaymentResult;
}
