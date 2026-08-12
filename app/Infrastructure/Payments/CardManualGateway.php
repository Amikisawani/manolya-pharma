<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Str;

final class CardManualGateway implements PaymentGateway
{
    public function charge(PaymentIntent $intent): PaymentResult
    {
        if (bccomp($intent->amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Card amount must be greater than zero.', 'card_manual');
        }

        return PaymentResult::ok(
            providerRef: $intent->reference ?? 'CARD-'.Str::upper(Str::random(10)),
            provider: 'card_manual',
        );
    }

    public function refund(string $providerRef, Money $amount): PaymentResult
    {
        if (bccomp($amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Refund amount must be greater than zero.', 'card_manual');
        }

        return PaymentResult::ok(
            providerRef: 'CARD-REF-'.$providerRef,
            provider: 'card_manual',
        );
    }
}
