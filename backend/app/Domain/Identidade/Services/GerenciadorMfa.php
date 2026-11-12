<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\GerenciarMfa;
use App\Domain\Identidade\Models\CodigoRecuperacao;
use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Support\Totp;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GerenciadorMfa implements GerenciarMfa
{
    private const QTD_CODIGOS_RECUPERACAO = 10;

    public function exige(Authenticatable $usuario): bool
    {
        return $this->comoUsuario($usuario)->vinculosVigentes()
            ->whereHas('papel', fn ($q) => $q->where('codigo', '!=', 'responsavel'))
            ->exists();
    }

    public function temFatorValidado(Authenticatable $usuario): bool
    {
        return FatorMfa::query()
            ->where('usuario_id', $this->comoUsuario($usuario)->id)
            ->whereNotNull('confirmado_em')
            ->exists();
    }

    public function iniciarInscricao(Authenticatable $usuario): array
    {
        $usuario = $this->comoUsuario($usuario);

        $segredo = Totp::gerarSegredo();

        // Renovar/substituir: descarta fatores TOTP anteriores (confirmado ou
        // pendente) antes de criar o novo pendente. Sem isso, a confirmação
        // viola o índice único parcial `fator_mfa_confirmado_unique`.
        FatorMfa::query()
            ->where('usuario_id', $usuario->id)
            ->where('tipo', FatorMfa::TIPO_TOTP)
            ->delete();

        FatorMfa::query()->create([
            'usuario_id' => $usuario->id,
            'tipo' => FatorMfa::TIPO_TOTP,
            'segredo_cifrado' => $segredo,
        ]);

        $rotulo = rawurlencode((string) $usuario->email_normalizado);
        $uri = sprintf(
            'otpauth://totp/EBI:%s?secret=%s&issuer=EBI',
            $rotulo,
            $segredo,
        );

        return ['segredo' => $segredo, 'uri' => $uri];
    }

    public function confirmar(Authenticatable $usuario, string $codigo): void
    {
        $usuario = $this->comoUsuario($usuario);

        $fator = FatorMfa::query()
            ->where('usuario_id', $usuario->id)
            ->where('tipo', FatorMfa::TIPO_TOTP)
            ->whereNull('confirmado_em')
            ->latest('id')
            ->first();

        if ($fator === null || ! Totp::validar((string) $fator->segredo_cifrado, $codigo)) {
            throw new ProblemException('mfa_invalido', 'Código inválido', 422, 'O código de verificação não confere.');
        }

        $fator->forceFill(['confirmado_em' => now()])->save();
    }

    public function gerarCodigosRecuperacao(Authenticatable $usuario): array
    {
        $usuario = $this->comoUsuario($usuario);

        return DB::transaction(function () use ($usuario): array {
            CodigoRecuperacao::query()->where('usuario_id', $usuario->id)->delete();

            $codigos = [];
            for ($i = 0; $i < self::QTD_CODIGOS_RECUPERACAO; $i++) {
                $codigo = strtoupper(Str::random(10));
                $codigos[] = $codigo;

                CodigoRecuperacao::query()->create([
                    'usuario_id' => $usuario->id,
                    'hash' => Hash::make($codigo),
                ]);
            }

            return $codigos;
        });
    }

    public function validar(Authenticatable $usuario, string $codigo): bool
    {
        $usuario = $this->comoUsuario($usuario);

        if ($this->validarTotp($usuario, $codigo)) {
            return true;
        }

        return $this->validarRecuperacao($usuario, $codigo);
    }

    private function validarTotp(Usuario $usuario, string $codigo): bool
    {
        $fator = FatorMfa::query()
            ->where('usuario_id', $usuario->id)
            ->where('tipo', FatorMfa::TIPO_TOTP)
            ->whereNotNull('confirmado_em')
            ->first();

        if ($fator === null) {
            return false;
        }

        return Totp::validar((string) $fator->segredo_cifrado, $codigo);
    }

    private function validarRecuperacao(Usuario $usuario, string $codigo): bool
    {
        $candidatos = CodigoRecuperacao::query()
            ->where('usuario_id', $usuario->id)
            ->whereNull('usado_em')
            ->get();

        foreach ($candidatos as $candidato) {
            if (Hash::check($codigo, (string) $candidato->hash)) {
                $candidato->forceFill(['usado_em' => now()])->save();

                return true;
            }
        }

        return false;
    }

    private function comoUsuario(Authenticatable $usuario): Usuario
    {
        if (! $usuario instanceof Usuario) {
            throw new ProblemException('usuario_invalido', 'Acesso negado', 403, 'Usuário de identidade inválido.');
        }

        return $usuario;
    }
}
