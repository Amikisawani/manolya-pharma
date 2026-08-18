<?php

namespace Tests\Unit;

use App\Support\FlexibleDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class FlexibleDateTest extends TestCase
{
    public function test_parses_french_and_iso_dates(): void
    {
        $this->assertSame('2027-12-31', FlexibleDate::toDateString('31/12/2027'));
        $this->assertSame('2027-12-31', FlexibleDate::toDateString('2027-12-31'));
        $this->assertSame('2027-06-01', FlexibleDate::toDateString(new DateTimeImmutable('2027-06-01')));
        $this->assertNull(FlexibleDate::toDateString('pas-une-date'));
        $this->assertNull(FlexibleDate::toDateString(null));
    }
}
