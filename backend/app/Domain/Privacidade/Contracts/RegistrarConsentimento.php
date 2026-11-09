<?php

namespace App\Domain\Privacidade\Contracts;

use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Support\EvidenciaAceite;

/**
 * Consentimento (CA-16): registrar aceite/recusa e a retirada, sempre com a
 * evidencia de onde a manifestacao partiu.
 */
interface RegistrarConsentimento
{
    public function registrar(
        string $usuarioId,
        string $termoVersaoId,
        string $decisao,
        string $origem,
        EvidenciaAceite $evidencia,
    ): ManifestacaoPrivacidade;

    public function retirar(
        string $usuarioId,
        string $termoVersaoId,
        EvidenciaAceite $evidencia,
    ): ManifestacaoPrivacidade;
}
