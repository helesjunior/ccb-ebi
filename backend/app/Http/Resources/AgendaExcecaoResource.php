<?php

namespace App\Http\Resources;

use App\Domain\Agenda\Contracts\ExcecaoAgenda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExcecaoAgenda */
class AgendaExcecaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ebi_id' => $this->ebiId,
            'agenda_ebi_id' => $this->agendaEbiId,
            'data' => $this->data,
            'tipo' => $this->tipo,
            'hora_inicio' => $this->horaInicio,
            'hora_fim' => $this->horaFim,
            'motivo' => $this->motivo,
        ];
    }
}
