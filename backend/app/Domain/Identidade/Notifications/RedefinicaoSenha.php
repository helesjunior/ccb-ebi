<?php

namespace App\Domain\Identidade\Notifications;

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Link de redefinicao de senha, enfileirado (Horizon). Carrega o token em
 * claro apenas no canal de e-mail; no banco fica o hash.
 */
class RedefinicaoSenha extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Usuario $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/')
            .'/redefinir-senha?token='.$this->token.'&email='.rawurlencode((string) $notifiable->email_normalizado);

        return (new MailMessage)
            ->subject('Redefinição de senha — EBI')
            ->line('Recebemos um pedido para redefinir sua senha.')
            ->action('Redefinir senha', $url)
            ->line('Se você não solicitou, ignore este e-mail.');
    }
}
