<?php

namespace App\Infrastructure\Payments;

final class OrangeMoneyGateway extends HttpMobileMoneyGateway
{
    protected function providerKey(): string
    {
        return 'orange';
    }
}
