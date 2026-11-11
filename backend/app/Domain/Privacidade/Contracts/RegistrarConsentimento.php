<?php

namespace App\Domain\Privacidade\Contracts;

use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;

/**
 * Consentimento (CA-16): registrar aceite/recusa e a retirada, sempre com a
 * evidencia de onde a manifestacao partiu.
 */
interface RegistrarConsentimento
{
    /**
     * @param  string|null  $usuarioId  nulo no cadastro assistido (sem conta)
     * @param  string|null  $participanteId  participante a quem o aceite se refere
     *
     * @throws ProblemException quando a versao de termo nao existe.
     */
    public function registrar(
        ?string $usuarioId,
        string $termoVersaoId,
        string $decisao,
        string $origem,
        EvidenciaAceite $evidencia,
        ?string $participanteId = null,
    ): ManifestacaoPrivacidade;

    /**
     * @throws ProblemException quando a versao de termo nao existe.
     */
    public function retirar(
        string $usuarioId,
        string $termoVersaoId,
        EvidenciaAceite $evidencia,
    ): ManifestacaoPrivacidade;
}
