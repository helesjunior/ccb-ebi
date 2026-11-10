<?php

namespace App\Domain\Privacidade\Services;

use App\Domain\Privacidade\Contracts\SolicitarDireitoTitular;
use App\Domain\Privacidade\Models\SolicitacaoTitular;
use Illuminate\Support\Str;

class GerenciadorSolicitacao implements SolicitarDireitoTitular
{
    public function abrir(string $solicitanteId, string $tipo, ?string $descricao): SolicitacaoTitular
    {
        return SolicitacaoTitular::query()->create([
            'solicitante_id' => $solicitanteId,
            'tipo' => $tipo,
            'protocolo' => $this->protocolo(),
            'status' => SolicitacaoTitular::STATUS_RECEBIDA,
            'descricao' => $descricao,
        ]);
    }

    /**
     * Protocolo curto e legivel para o titular, derivado de um ULID.
     */
    private function protocolo(): string
    {
        return strtoupper((string) Str::ulid());
    }
}
