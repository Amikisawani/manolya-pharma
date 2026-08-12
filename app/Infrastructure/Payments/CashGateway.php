<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Str;

final class CashGateway implements PaymentGateway
{
    public function charge(PaymentIntent $intent): PaymentResult
    {
        if ($intent->amount->isZero() || bccomp($intent->amount->amount, '0', 2) < 0) {
            return PaymentResult::failed('Cash amount must be greater than zero.', 'cash');
        }

        return PaymentResult::ok(
            providerRef: 'CASH-'.Str::upper(Str::random(10)),
            provider: 'cash',
        );
    }

    public function refund(string $providerRef, Money $amount): PaymentResult
    {
        if (bccomp($amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Refund amount must be greater than zero.', 'cash');
        }

        return PaymentResult::ok(
            providerRef: 'CASH-REF-'.$providerRef,
            provider: 'cash',
        );
    }
}
