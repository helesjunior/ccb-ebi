<?php

namespace App\Domain\Identidade\Notifications;

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Link de verificacao de e-mail, enfileirado (Horizon). A URL e assinada
 * (temporarySignedRoute) e aponta para a rota `auth.verify-email`.
 */
class VerificacaoEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'auth.verify-email',
            now()->addHours(24),
            ['usuario' => $notifiable->id],
        );

        return (new MailMessage)
            ->subject('Confirme seu e-mail — EBI')
            ->line('Confirme seu e-mail para ativar o acesso.')
            ->action('Confirmar e-mail', $url);
    }
}
