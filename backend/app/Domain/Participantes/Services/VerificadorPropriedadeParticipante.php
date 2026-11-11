<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Participantes\Models\ResponsavelParticipante;

/**
 * Autorizacao por **propriedade** (CA-04): o responsavel acessa o participante
 * que ele representa, nao por territorio nem por conhecer a matricula.
 *
 * O gate e `responsavel_participante` com `usuario_id` do ator,
 * `validacao = aprovada` e vinculo vigente (`fim IS NULL`). Contestar/recusar
 * derruba o acesso imediatamente (RN-15).
 */
class VerificadorPropriedadeParticipante
{
    public function podeAcessar(string $usuarioId, string $participanteId): bool
    {
        if ($usuarioId === '' || $participanteId === '') {
            return false;
        }

        return ResponsavelParticipante::query()
            ->where('usuario_id', $usuarioId)
            ->where('participante_id', $participanteId)
            ->where('validacao', ResponsavelParticipante::VALIDACAO_APROVADA)
            ->whereNull('fim')
            ->exists();
    }
}
