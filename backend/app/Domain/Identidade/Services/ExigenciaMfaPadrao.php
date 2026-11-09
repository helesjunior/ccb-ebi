<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Models\Usuario;

/**
 * Politica conservadora de MFA (ate a Task 8): todo perfil que nao seja apenas
 * `responsavel` exige segundo fator e ainda nao possui fator validado (nenhum
 * fator existe antes da Task 8). A Task 8 substitui esta implementacao pela que
 * consulta `fator_mfa`.
 */
class ExigenciaMfaPadrao implements ExigirMfa
{
    public function exige(Usuario $usuario): bool
    {
        return $usuario->vinculosVigentes()
            ->whereHas('papel', fn ($q) => $q->where('codigo', '!=', 'responsavel'))
            ->exists();
    }

    public function temFatorValidado(Usuario $usuario): bool
    {
        return false;
    }
}
