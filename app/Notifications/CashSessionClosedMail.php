<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class CashSessionClosedMail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public string $pdfPath,
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
        $number = $this->payload['session']['number'] ?? 'session';
        $site = $this->payload['session']['site'] ?? 'Site';
        $cashier = $this->payload['cashier']['name'] ?? '—';
        $cashbox = $this->payload['cashbox'] ?? [];

        $mail = (new MailMessage)
            ->subject('Clôture caisse '.$number.' — '.$site)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('La session de caisse '.$number.' vient d’être clôturée.')
            ->line('Caissier(ère) : '.$cashier)
            ->line('Fond de caisse : '.$fmt($cashbox['opening_float'] ?? 0).' Fc')
            ->line('Espèces attendues : '.$fmt($cashbox['expected_cash'] ?? 0).' Fc')
            ->line('Espèces comptées : '.$fmt($cashbox['closing_counted'] ?? 0).' Fc')
            ->line('Écart : '.$fmt($cashbox['variance'] ?? 0).' Fc')
            ->line('Le détail (articles vendus, paiements, ruptures de stock) est en pièce jointe PDF.')
            ->action('Voir les sessions', url('/pos/sessions'))
            ->salutation('— Manolya Pharma');

        if ($this->pdfPath !== '' && Storage::disk('local')->exists($this->pdfPath)) {
            $mail->attachData(
                Storage::disk('local')->get($this->pdfPath),
                'cloture-caisse-'.$number.'.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $mail;
    }
}
