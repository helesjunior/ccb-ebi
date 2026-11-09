<?php

namespace App\Domain\Identidade\Notifications;

use App\Domain\Identidade\Models\Convite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Convite para a equipe, enfileirado. O token em claro vai apenas no link;
 * no banco fica o hash (`convite.token_hash`).
 */
class ConviteEbi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Convite $convite, public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/').'/convites/'.$this->token;

        return (new MailMessage)
            ->subject('Convite para a equipe — EBI')
            ->line('Você foi convidado(a) para a equipe da EBI.')
            ->action('Aceitar convite', $url)
            ->line('O convite expira em '.$this->convite->expira_em->format('d/m/Y').'.');
    }
}
