<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\AceitarConvite;
use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Aceita um convite sem login: o token secreto (hash em `convite.token_hash`)
 * é a credencial. Em transação cria a conta verificada (posse do token comprova
 * o e-mail) e o vínculo vigente, e marca `aceito_em` (uso único). Replay => 410.
 */
class AceitadorConvite implements AceitarConvite
{
    public function aceitar(string $conviteId, string $token, string $nome, string $senha): ContaCriada
    {
        return DB::transaction(function () use ($conviteId, $token, $nome, $senha): ContaCriada {
            $convite = Convite::query()->lockForUpdate()->find($conviteId);

            if ($convite === null || ! $convite->pendente()) {
                throw new ProblemException('convite_ja_aceito', 'Convite indisponível', 410, 'O convite não está mais disponível.');
            }

            if (! hash_equals((string) $convite->token_hash, hash('sha256', $token))) {
                throw new ProblemException('convite_ja_aceito', 'Convite indisponível', 410, 'O token do convite é inválido.');
            }

            $usuario = Usuario::query()->create([
                'nome' => $nome,
                'email_normalizado' => $convite->email_normalizado,
                'email_verificado_em' => now(),
                'senha' => Hash::make($senha),
                'status' => 'ativo',
            ]);

            VinculoAcesso::query()->create([
                'usuario_id' => $usuario->id,
                'papel_id' => $convite->papel_id,
                'tipo_escopo' => $convite->tipo_escopo,
                'escopo_id' => $convite->escopo_id,
                'inicio' => now()->toDateString(),
                'fim' => null,
                'status' => 'ativo',
            ]);

            $convite->forceFill(['aceito_em' => now()])->save();

            return new ContaCriada(
                id: (string) $usuario->id,
                emailNormalizado: (string) $usuario->email_normalizado,
                nome: (string) $usuario->nome,
            );
        });
    }
}
