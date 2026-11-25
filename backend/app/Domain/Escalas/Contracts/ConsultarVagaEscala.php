<?php

namespace App\Domain\Escalas\Contracts;

/**
 * Leitura das vagas abertas de uma escala (RF-07, evolucao). Toda projecao ja
 * vem decidida pela camada HTTP (`ProjecaoVaga`); os nomes saem conforme ela.
 */
interface ConsultarVagaEscala
{
    /**
     * Vagas do EBI, opcionalmente por status e periodo do inicio da sessao.
     *
     * @return list<array<string, mixed>>
     */
    public function doEbi(string $ebiId, ?string $inicio, ?string $fim, ?string $status, ProjecaoVaga $projecao): array;

    /**
     * Vagas da escala, em ordem de abertura.
     *
     * @return list<array<string, mixed>>
     */
    public function daEscala(string $escalaId, ProjecaoVaga $projecao, ?string $leitorId): array;

    /**
     * Vagas abertas e nao expiradas dos EBIs em que a pessoa tem vinculo de
     * equipe vigente hoje, exceto escalas em que ela ja e membro ativo.
     *
     * @param  list<array{ebi_id: ?string, igreja_id: string}>  $escopos
     * @return list<array<string, mixed>>
     */
    public function paraMim(string $usuarioId, array $escopos, string $inicio, string $fim): array;

    /**
     * Vaga escolhida (ja preenchida) vista pela projecao pedida.
     *
     * @return array<string, mixed>|null
     */
    public function uma(string $vagaId, ProjecaoVaga $projecao, ?string $leitorId): ?array;
}
