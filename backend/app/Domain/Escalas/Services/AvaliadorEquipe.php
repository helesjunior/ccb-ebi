<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\EquipeMinima\CalculadoraEquipeMinima;
use Illuminate\Support\Collection;

/**
 * Quem esta de fato escalado para uma sessao: o membro precisa estar ativo na
 * escala e ter papel vigente na data local da sessao, na funcao em que foi
 * escalado (quem era pecado como coordenadora nao conta como auxiliar).
 */
class AvaliadorEquipe
{
    /** Papeis aceitos por funcao da escala. */
    private const PAPEIS_POR_FUNCAO = [
        EscalaMembro::FUNCAO_COORDENADORA => ['coordenadora', 'coordenadora_chefe'],
        EscalaMembro::FUNCAO_AUXILIAR => ['auxiliar', 'coordenadora', 'coordenadora_chefe'],
    ];

    public function __construct(
        private readonly ConsultarEquipeEbi $equipe,
        private readonly CalculadoraEquipeMinima $calculadora,
    ) {}

    /**
     * @param  array<int, array{papel: string, inicio: string, fim: ?string}>  $vigencias
     */
    public function vigenteEm(array $vigencias, string $dataLocal, string $funcao): bool
    {
        $papeis = self::PAPEIS_POR_FUNCAO[$funcao] ?? [];

        foreach ($vigencias as $vigencia) {
            if (! in_array($vigencia['papel'], $papeis, true)) {
                continue;
            }

            $inicio = substr($vigencia['inicio'], 0, 10);
            $fim = $vigencia['fim'] === null ? null : substr($vigencia['fim'], 0, 10);

            if ($inicio <= $dataLocal && ($fim === null || $fim >= $dataLocal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Membros ativos sem vigencia na data; o id e o do vinculo de escala.
     *
     * @return array<string, true>
     */
    public function semVinculo(Escala $escala, IntervaloSessao $sessao): array
    {
        $ativos = $this->membrosAtivos($escala);
        $vigencias = $this->vigenciasDe($ativos, $sessao);

        $semVinculo = [];

        foreach ($ativos as $membro) {
            if (! $this->vigenteEm($vigencias[(string) $membro->usuario_id] ?? [], $sessao->dataLocal, (string) $membro->funcao)) {
                $semVinculo[(string) $membro->id] = true;
            }
        }

        return $semVinculo;
    }

    /**
     * @return array{coordenadoras: int, auxiliares: int}
     */
    public function contagem(Escala $escala, IntervaloSessao $sessao, bool $soConfirmados = false): array
    {
        $ativos = $this->membrosAtivos($escala);
        $vigencias = $this->vigenciasDe($ativos, $sessao);

        $contagem = ['coordenadoras' => 0, 'auxiliares' => 0];

        foreach ($ativos as $membro) {
            if ($soConfirmados && ! $this->confirmado($membro)) {
                continue;
            }

            if (! $this->vigenteEm($vigencias[(string) $membro->usuario_id] ?? [], $sessao->dataLocal, (string) $membro->funcao)) {
                continue;
            }

            $contagem[$membro->funcao === EscalaMembro::FUNCAO_COORDENADORA ? 'coordenadoras' : 'auxiliares'] += 1;
        }

        return $contagem;
    }

    /**
     * @return array{estimativa:int, participantes_por_auxiliar:int, coordenadoras_necessarias:int, auxiliares_necessarios:int, coordenadoras_escaladas:int, auxiliares_escalados:int, atingida:bool}
     */
    public function equipeMinima(Escala $escala, IntervaloSessao $sessao, bool $soConfirmados = false): array
    {
        $contagem = $this->contagem($escala, $sessao, $soConfirmados);

        return $this->calculadora->paraEscala(
            (int) $escala->estimativa_participantes,
            (int) $escala->participantes_por_auxiliar,
            $contagem['coordenadoras'],
            $contagem['auxiliares'],
        );
    }

    /**
     * Tipos dos membros ativos da escala (nao carrega o model inteiro alem do
     * necessario).
     *
     * @return Collection<int, EscalaMembro>
     */
    private function membrosAtivos(Escala $escala): Collection
    {
        return $escala->membros()->ativos()->get(['id', 'escala_id', 'usuario_id', 'funcao', 'status', 'confirmado_em']);
    }

    /**
     * @param  Collection<int, EscalaMembro>  $membros
     * @return array<string, array<int, array{papel: string, inicio: string, fim: ?string}>>
     */
    private function vigenciasDe(Collection $membros, IntervaloSessao $sessao): array
    {
        $ids = $membros->pluck('usuario_id')->map(fn ($id): string => (string) $id)->unique()->values()->all();

        return $this->equipe->vigencias($ids, $sessao->ebiId, $sessao->igrejaId);
    }

    private function confirmado(EscalaMembro $membro): bool
    {
        return $membro->status === EscalaMembro::STATUS_CONFIRMADO && $membro->confirmado_em !== null;
    }
}
