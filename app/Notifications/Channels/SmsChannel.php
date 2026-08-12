<?php

namespace App\Notifications\Channels;

use App\Infrastructure\Sms\SmsGateway;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

final class SmsChannel
{
    public function __construct(private readonly SmsGateway $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms')
            ?? $notifiable->phone
            ?? null;

        if (! is_string($to) || trim($to) === '') {
            Log::info('SMS skipped: no phone on notifiable', [
                'notifiable' => $notifiable::class,
                'id' => $notifiable->id ?? null,
            ]);

            return;
        }

        $result = $this->sms->send($to, $message, [
            'notification' => $notification::class,
            'notifiable_id' => $notifiable->id ?? null,
        ]);

        if (! $result->successful) {
            Log::warning('SMS send failed', [
                'provider' => $result->provider,
                'message' => $result->message,
                'to' => $to,
            ]);
        }
    }
}
