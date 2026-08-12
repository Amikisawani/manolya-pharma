<?php

namespace App\Infrastructure\Sms;

final class OrangeSmsGateway extends HttpSmsGateway
{
    protected function providerKey(): string
    {
        return 'orange';
    }
}
