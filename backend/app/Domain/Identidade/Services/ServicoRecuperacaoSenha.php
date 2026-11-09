<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\RecuperarSenha;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Notifications\RedefinicaoSenha;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Recuperacao de senha sem enumeracao de contas. O token em claro vai somente
 * na notificacao; no banco `password_reset_tokens` guarda o hash.
 */
class ServicoRecuperacaoSenha implements RecuperarSenha
{
    private const VALIDADE_MINUTOS = 60;

    public function solicitar(string $email): void
    {
        $emailNormalizado = Usuario::emailNormalizado($email);
        $usuario = Usuario::query()->where('email_normalizado', $emailNormalizado)->first();

        if ($usuario === null) {
            return;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $emailNormalizado],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        $usuario->notify(new RedefinicaoSenha($token));
    }

    public function redefinir(string $email, string $token, string $novaSenha): void
    {
        $emailNormalizado = Usuario::emailNormalizado($email);

        $registro = DB::table('password_reset_tokens')->where('email', $emailNormalizado)->first();

        $expirado = $registro !== null
            && $registro->created_at !== null
            && now()->diffInMinutes($registro->created_at, true) > self::VALIDADE_MINUTOS;

        if ($registro === null || $expirado || ! Hash::check($token, (string) $registro->token)) {
            throw new ProblemException('token_invalido', 'Token inválido', 422, 'O token de redefinição é inválido ou expirou.');
        }

        $usuario = Usuario::query()->where('email_normalizado', $emailNormalizado)->first();

        if ($usuario === null) {
            throw new ProblemException('token_invalido', 'Token inválido', 422, 'O token de redefinição é inválido ou expirou.');
        }

        DB::transaction(function () use ($usuario, $emailNormalizado, $novaSenha): void {
            $usuario->forceFill(['senha' => Hash::make($novaSenha)])->save();

            // Forca re-login: revoga todas as sessoes (tokens Sanctum).
            $usuario->tokens()->delete();

            DB::table('password_reset_tokens')->where('email', $emailNormalizado)->delete();
        });
    }
}
