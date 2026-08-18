<?php

namespace Tests\Unit\Domain;

use App\Domain\Shared\Formatting\MoneyFormatter;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_formats_franc_congolais_with_grouped_thousands(): void
    {
        $formatter = new MoneyFormatter;

        $this->assertSame('1 500 Fc', $formatter->format('1500.00'));
        $this->assertSame('25 000 Fc', $formatter->format(25000));
        $this->assertSame('125 500 Fc', $formatter->format('125500'));
        $this->assertSame('1 500,50 Fc', $formatter->format('1500.50'));
    }

    public function test_formats_quantities_without_trailing_zeroes(): void
    {
        $formatter = new MoneyFormatter;

        $this->assertSame('2', $formatter->formatQuantity('2.000'));
        $this->assertSame('2.5', $formatter->formatQuantity('2.500'));
        $this->assertSame('12', $formatter->formatQuantity(12));
    }
}
