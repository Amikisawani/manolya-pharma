<?php

namespace App\Infrastructure\Payments;

final class MtnMomoGateway extends HttpMobileMoneyGateway
{
    protected function providerKey(): string
    {
        return 'mtn';
    }
}
