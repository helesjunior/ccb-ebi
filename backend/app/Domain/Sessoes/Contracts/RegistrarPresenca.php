<?php

namespace App\Domain\Sessoes\Contracts;

interface RegistrarPresenca
{
    public function registrar(string $sessaoId, string $participanteId, string $usuarioId, string $origem = 'web'): ResultadoPresenca;

    public function registrarContingencia(string $sessaoId, string $participanteId, string $motivo, string $usuarioId): ResultadoPresenca;

    /**
     * Lista em papel: cada linha roda como uma contingencia individual e falha
     * sozinha. Sessao inexistente (404) ou nao encerrada (409) barra o lote todo.
     *
     * @param  array<int, string>  $participanteIds  distintos, 1..100
     * @return array<int, array{participante_id: string, resultado: 'criada'|'ja_existia'|'erro', erro?: string, motivo_bloqueio?: ?string, presenca?: PresencaRegistrada}>
     */
    public function registrarContingenciaLote(string $sessaoId, array $participanteIds, string $motivo, string $usuarioId): array;
}
