<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use Illuminate\Contracts\Auth\Authenticatable;

class ConsultaVinculos implements ConsultarVinculos
{
    public function vigentesDoUsuario(Authenticatable $usuario): array
    {
        if (! $usuario instanceof Usuario) {
            return [];
        }

        return $usuario->vinculosVigentes()
            ->with('papel')
            ->orderByDesc('inicio')
            ->get()
            ->map(fn (VinculoAcesso $vinculo): array => [
                'papel' => (string) $vinculo->papel?->codigo,
                'tipo_escopo' => (string) $vinculo->tipo_escopo,
                'escopo_id' => (string) $vinculo->escopo_id,
            ])
            ->values()
            ->all();
    }
}
