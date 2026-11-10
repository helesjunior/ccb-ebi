<?php

namespace App\Domain\Privacidade\Contracts;

use App\Domain\Privacidade\Models\SolicitacaoTitular;

/**
 * Solicitacao de direitos do titular (RF-16): abre um pedido de acesso,
 * correcao ou revogacao em nome do proprio titular, gerando o protocolo.
 */
interface SolicitarDireitoTitular
{
    public function abrir(string $solicitanteId, string $tipo, ?string $descricao): SolicitacaoTitular;
}
