<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CriticalAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (config('services.sms.enabled', false)
            && in_array($this->alert->severity, ['critical', 'high'], true)
            && filled($notifiable->phone ?? null)
        ) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Critique] '.$this->alert->title)
            ->greeting('Alerte Manolya Pharma')
            ->line($this->alert->body ?? $this->alert->title)
            ->action('Voir les alertes', url('/alerts'))
            ->salutation('— Manolya Pharma');
    }

    public function toSms(object $notifiable): string
    {
        $body = $this->alert->body ?? $this->alert->title;

        return 'Manolya: '.mb_substr($this->alert->title.' — '.$body, 0, 300);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'type' => $this->alert->type,
            'severity' => $this->alert->severity,
            'title' => $this->alert->title,
            'body' => $this->alert->body,
        ];
    }
}
