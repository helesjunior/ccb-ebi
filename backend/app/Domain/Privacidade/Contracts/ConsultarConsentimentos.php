<?php

namespace App\Domain\Privacidade\Contracts;

use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;

/**
 * Leitura do consentimento atual do titular: a ultima manifestacao por
 * finalidade de termo.
 */
interface ConsultarConsentimentos
{
    /** @return array<int, ManifestacaoPrivacidade> */
    public function atuais(string $usuarioId): array;

    public function encontrar(string $manifestacaoId): ?ManifestacaoPrivacidade;
}
