<?php

namespace App\Http\Resources;

use App\Domain\Agenda\Contracts\FaixaAgenda;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FaixaAgenda */
class AgendaFaixaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ebi_id' => $this->ebiId,
            'dia_semana' => $this->diaSemana,
            'hora_inicio' => $this->horaInicio,
            'hora_fim' => $this->horaFim,
            'inicio_vigencia' => $this->inicioVigencia,
            'fim_vigencia' => $this->fimVigencia,
            'vigente' => $this->vigente,
        ];
    }
}
