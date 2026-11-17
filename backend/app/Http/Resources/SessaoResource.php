<?php

namespace App\Http\Resources;

use App\Domain\Sessoes\Contracts\SessaoEbi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SessaoEbi */
class SessaoResource extends JsonResource
{
    public function __construct($resource, private readonly ?array $equipeMinima = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'ebi_id' => $this->ebiId, 'ebi_nome' => $this->ebiNome,
            'igreja_id' => $this->igrejaId, 'agenda_ebi_id' => $this->agendaEbiId,
            'inicio_previsto' => $this->inicioPrevisto, 'fim_previsto' => $this->fimPrevisto,
            'inicio_real' => $this->inicioReal, 'fim_real' => $this->fimReal,
            'tema' => $this->tema, 'passagem_biblica' => $this->passagemBiblica,
            'status' => $this->status, 'realizada' => $this->realizada,
            'motivo_cancelamento' => $this->motivoCancelamento, 'auxiliares_escalados' => $this->auxiliaresEscalados,
            'presentes' => $this->presentes,
            'equipe_minima' => $this->when($this->equipeMinima !== null, $this->equipeMinima),
        ];
    }
}
