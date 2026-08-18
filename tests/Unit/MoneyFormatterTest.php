<?php

namespace Tests\Unit;

use App\Domain\Shared\Formatting\MoneyFormatter;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_formats_francs_without_useless_decimals(): void
    {
        $formatter = new MoneyFormatter;

        $this->assertSame('5 000 Fc', $formatter->format('5000.00'));
        $this->assertSame('2', $formatter->formatQuantity('2.000'));
    }
}
