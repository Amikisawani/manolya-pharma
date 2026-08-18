<?php

namespace App\Notifications;

use App\Models\CashRegisterSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class CashSessionCloseRequestedMail extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CashRegisterSession $session,
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
        $this->session->loadMissing(['opener', 'site']);
        $number = $this->session->number;
        $cashier = $this->session->opener?->name ?? 'Caisse';

        return (new MailMessage)
            ->subject('Demande de fermeture caisse '.$number)
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($cashier.' demande la clôture de la session '.$number.'.')
            ->line('Site : '.($this->session->site?->name ?? '—'))
            ->line('Espèces comptées : '.number_format((float) $this->session->closing_counted, 0, ',', ' ').' Fc')
            ->action('Confirmer la clôture', url('/reports/cash-sessions/'.$this->session->id))
            ->salutation('— Manolya Pharma');
    }
}
