<?php

namespace App\Infrastructure\Sms;

final class AirtelSmsGateway extends HttpSmsGateway
{
    protected function providerKey(): string
    {
        return 'airtel';
    }
}
