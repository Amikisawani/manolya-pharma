<?php

namespace App\Infrastructure\Sms;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message, array $context = []): SmsResult
    {
        $ref = 'LOG-SMS-'.Str::upper(Str::random(8));

        Log::info('SMS gateway (log)', [
            'to' => $to,
            'message' => $message,
            'context' => $context,
            'ref' => $ref,
        ]);

        return SmsResult::ok('log', $ref);
    }
}
