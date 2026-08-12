<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class Money implements Stringable
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $this->amount)) {
            throw new InvalidArgumentException('Money amount must be a decimal string with up to 2 fraction digits.');
        }

        if (! preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
    }

    public static function of(string|float|int $amount, string $currency): self
    {
        if (is_float($amount) || is_int($amount)) {
            $amount = number_format((float) $amount, 2, '.', '');
        }

        return new self($amount, strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::of(bcadd($this->amount, $other->amount, 2), $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::of(bcsub($this->amount, $other->amount, 2), $this->currency);
    }

    public function multiply(string $factor): self
    {
        return self::of(bcmul($this->amount, $factor, 2), $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency
            && bccomp($this->amount, $other->amount, 2) === 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', 2) === 0;
    }

    public function __toString(): string
    {
        return $this->amount.' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }
}
