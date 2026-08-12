<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Str;

/**
 * Stub adapter for Orange Money / Airtel Money / MTN MoMo until real APIs are wired.
 */
final class MobileMoneyStubGateway implements PaymentGateway
{
    public function charge(PaymentIntent $intent): PaymentResult
    {
        if (bccomp($intent->amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Mobile money amount must be greater than zero.', 'mobile_money');
        }

        $provider = $intent->provider ?: 'mobile_money_stub';

        return PaymentResult::ok(
            providerRef: $intent->reference ?? 'MOMO-'.Str::upper(Str::random(10)),
            provider: $provider,
        );
    }

    public function refund(string $providerRef, Money $amount): PaymentResult
    {
        if (bccomp($amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Refund amount must be greater than zero.', 'mobile_money');
        }

        return PaymentResult::ok(
            providerRef: 'MOMO-REF-'.$providerRef,
            provider: 'mobile_money_stub',
        );
    }
}
