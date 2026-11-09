<?php

namespace App\Domain\Identidade\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Leitura dos vinculos de acesso do proprio usuario (RF-05): alimenta o seletor
 * de contexto de atuacao do portal (`/me/scopes`). Devolve dados serializaveis
 * (nunca modelos), mantendo `app/Http` livre dos modelos do modulo (ADR-0002).
 */
interface ConsultarVinculos
{
    /**
     * Vinculos vigentes do usuario, do mais recente ao mais antigo.
     *
     * @return array<int, array{papel: string, tipo_escopo: string, escopo_id: string}>
     */
    public function vigentesDoUsuario(Authenticatable $usuario): array;
}
