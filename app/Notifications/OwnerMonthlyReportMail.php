<?php

namespace App\Notifications;

use App\Models\ReportRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class OwnerMonthlyReportMail extends Notification implements ShouldQueue
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
        $label = $this->payload['period_label'] ?? $this->reportRun->period_start?->format('Y-m');

        $mail = (new MailMessage)
            ->subject('Rapport mensuel Manolya Pharma — '.$label)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Voici le bilan de '.$this->payload['tenant'].' pour '.$label.'.')
            ->line('CA : '.$fmt($this->payload['ca'] ?? 0).' Fc ('.$this->payload['deltas']['ca_pct'].'% vs mois précédent)')
            ->line('Marge : '.$fmt($this->payload['profit'] ?? 0).' Fc')
            ->line('Dépenses : '.$fmt($this->payload['expenses'] ?? 0).' Fc')
            ->line('Net : '.$fmt($this->payload['net'] ?? 0).' Fc · Stock : '.$fmt($this->payload['stock_value'] ?? 0).' Fc')
            ->action('Ouvrir Finance', url('/finance'))
            ->salutation('— Manolya Pharma');

        $path = $this->pdfPath ?? $this->reportRun->disk_path;
        if ($path && Storage::disk('local')->exists($path)) {
            $mail->attachData(
                Storage::disk('local')->get($path),
                'rapport-mensuel-'.($this->payload['period_start'] ?? 'mois').'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
