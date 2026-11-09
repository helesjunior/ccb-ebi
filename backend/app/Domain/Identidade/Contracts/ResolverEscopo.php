<?php

namespace App\Domain\Identidade\Contracts;

/**
 * Nucleo do ABAC territorial: decide se um recurso esta dentro da subarvore do
 * no apontado por um vinculo de acesso (o poder desce a hierarquia).
 */
interface ResolverEscopo
{
    /**
     * @param  string  $tipoEscopoVinculo  Tipo do no do vinculo (central|regional|setor|igreja|ebi).
     * @param  string  $escopoIdVinculo  ID do no do vinculo.
     * @param  string  $tipoRecurso  Tipo do recurso-alvo.
     * @param  string  $recursoId  ID do recurso-alvo.
     * @return bool true se o recurso e o no do vinculo ou um descendente dele.
     */
    public function cobre(
        string $tipoEscopoVinculo,
        string $escopoIdVinculo,
        string $tipoRecurso,
        string $recursoId,
    ): bool;
}
