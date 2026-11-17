<?php

namespace App\Http\Resources;

use App\Domain\Sessoes\Contracts\PresencaRegistrada;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PresencaRegistrada */
class PresencaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'sessao_id' => $this->sessaoId, 'participante_id' => $this->participanteId,
            'participante_nome' => $this->participanteNome, 'matricula' => $this->matricula,
            'registrado_por' => $this->registradoPor, 'registrado_em' => $this->registradoEm,
            'origem' => $this->origem, 'status' => $this->status,
            'estornado_em' => $this->estornadoEm, 'motivo_estorno' => $this->motivoEstorno,
        ];
    }
}
