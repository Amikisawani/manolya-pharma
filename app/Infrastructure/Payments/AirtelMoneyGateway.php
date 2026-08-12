<?php

namespace App\Infrastructure\Payments;

final class AirtelMoneyGateway extends HttpMobileMoneyGateway
{
    protected function providerKey(): string
    {
        return 'airtel';
    }
}
