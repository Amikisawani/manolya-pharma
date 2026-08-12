<?php

namespace App\Notifications;

use App\Models\ReportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class OwnerDailyReportMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public ReportRun $reportRun,
        public array $payload,
        public ?string $pdfPath = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $day = $this->payload['day'] ?? $this->reportRun->period_start?->toDateString();

        $mail = (new MailMessage)
            ->subject('Rapport quotidien Manolya Pharma — '.$day)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Voici le résumé d’activité de '.$this->payload['tenant'].' pour le '.$day.'.')
            ->line('CA : '.$fmt($this->payload['ca'] ?? 0).' Fc')
            ->line('Marge : '.$fmt($this->payload['profit'] ?? 0).' Fc')
            ->line('Dépenses : '.$fmt($this->payload['expenses'] ?? 0).' Fc')
            ->line('Ventes : '.($this->payload['sales_count'] ?? 0).' · Panier moyen : '.$fmt($this->payload['avg_basket'] ?? 0).' Fc')
            ->action('Ouvrir le tableau de bord', url('/dashboard'))
            ->salutation('— Manolya Pharma');

        $path = $this->pdfPath ?? $this->reportRun->disk_path;
        if ($path && Storage::disk('local')->exists($path)) {
            $mail->attachData(
                Storage::disk('local')->get($path),
                'rapport-quotidien-'.$day.'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
