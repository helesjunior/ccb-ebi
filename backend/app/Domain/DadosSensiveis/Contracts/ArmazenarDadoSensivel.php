<?php

namespace App\Domain\DadosSensiveis\Contracts;

use App\Domain\DadosSensiveis\Models\DadoSensivelParticipante;

/**
 * Armazena um dado sensível de participante.
 *
 * O conteúdo sensível é cifrado com a chave própria do módulo (SENSITIVE_DATA_KEY),
 * nunca com a APP_KEY. Alternativamente, pode-se guardar apenas uma referência
 * segura (ex.: ponteiro para um cofre externo), sem conteúdo em claro.
 */
interface ArmazenarDadoSensivel
{
    /**
     * Persiste um dado sensível vinculado (ou não) a um participante.
     *
     * Exatamente um entre $conteudo e $referenciaSegura deve estar preenchido:
     * quando $conteudo estiver preenchido, ele é cifrado e gravado em `conteudo_cifrado`.
     *
     * @param  string  $conteudo  Texto sensível em claro (cifrado na gravação). Vazio quando usar referência segura.
     * @param  string|null  $referenciaSegura  Referência a um cofre externo. Nulo quando usar conteúdo.
     * @param  \DateTimeInterface|null  $prazoRetencao  Data limite de retenção (nulo = sem prazo definido).
     */
    public function armazenar(
        ?string $participanteId,
        string $tipo,
        string $conteudo,
        ?string $referenciaSegura,
        string $finalidade,
        string $baseLegal,
        string $controleAcesso,
        ?\DateTimeInterface $prazoRetencao,
    ): DadoSensivelParticipante;
}
