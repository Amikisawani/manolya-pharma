<?php

namespace App\Infrastructure\Payments;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP Mobile Money adapter (Orange Money / Airtel Money / MTN MoMo).
 * When credentials are missing, falls back to MobileMoneyStubGateway.
 */
abstract class HttpMobileMoneyGateway implements PaymentGateway
{
    abstract protected function providerKey(): string;

    public function __construct(
        private readonly MobileMoneyStubGateway $stub,
    ) {}

    public function charge(PaymentIntent $intent): PaymentResult
    {
        $cfg = config('services.momo.'.$this->providerKey(), []);
        $url = $cfg['charge_url'] ?? null;
        $token = $cfg['token'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            return $this->stub->charge(new PaymentIntent(
                amount: $intent->amount,
                method: $intent->method,
                provider: $this->providerKey(),
                reference: $intent->reference,
                metadata: $intent->metadata,
            ));
        }

        if (bccomp($intent->amount->amount, '0', 2) <= 0) {
            return PaymentResult::failed('Montant MoMo invalide.', $this->providerKey());
        }

        try {
            $response = Http::timeout((int) ($cfg['timeout'] ?? 45))
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'amount' => $intent->amount->amount,
                    'currency' => $intent->amount->currency,
                    'reference' => $intent->reference,
                    'provider' => $this->providerKey(),
                    'msisdn' => $intent->metadata['msisdn'] ?? null,
                    'metadata' => $intent->metadata,
                ]);

            if (! $response->successful()) {
                return PaymentResult::failed(
                    'MoMo HTTP '.$response->status(),
                    $this->providerKey(),
                );
            }

            $status = strtolower((string) ($response->json('status') ?? 'success'));
            if (in_array($status, ['failed', 'error', 'rejected'], true)) {
                return PaymentResult::failed(
                    (string) ($response->json('message') ?? 'Paiement MoMo refusé'),
                    $this->providerKey(),
                );
            }

            $ref = (string) ($response->json('transaction_id')
                ?? $response->json('id')
                ?? 'MOMO-'.Str::upper(Str::random(10)));

            return PaymentResult::ok($ref, $this->providerKey());
        } catch (\Throwable $e) {
            Log::error('MoMo charge failed', [
                'provider' => $this->providerKey(),
                'error' => $e->getMessage(),
            ]);

            return PaymentResult::failed($e->getMessage(), $this->providerKey());
        }
    }

    public function refund(string $providerRef, Money $amount): PaymentResult
    {
        $cfg = config('services.momo.'.$this->providerKey(), []);
        $url = $cfg['refund_url'] ?? null;
        $token = $cfg['token'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($token) || $token === '') {
            return $this->stub->refund($providerRef, $amount);
        }

        try {
            $response = Http::timeout((int) ($cfg['timeout'] ?? 45))
                ->withToken($token)
                ->acceptJson()
                ->post($url, [
                    'transaction_id' => $providerRef,
                    'amount' => $amount->amount,
                    'currency' => $amount->currency,
                ]);

            if (! $response->successful()) {
                return PaymentResult::failed('MoMo refund HTTP '.$response->status(), $this->providerKey());
            }

            return PaymentResult::ok(
                (string) ($response->json('refund_id') ?? 'MOMO-REF-'.$providerRef),
                $this->providerKey(),
            );
        } catch (\Throwable $e) {
            return PaymentResult::failed($e->getMessage(), $this->providerKey());
        }
    }
}
