<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Contracts\GerirVagaEscala;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\IntervaloSessao;
use App\Support\ProblemException;

/**
 * Ciclo da vaga (RF-07, evolucao, decisao 5 da spec). A dona abre; qualquer
 * membro de equipe do EBI vigente hoje oferece; so a dona escolhe — a chefe e
 * avisada pelo painel, nunca aprova.
 *
 * Autorizacao atual (vinculo de equipe vigente hoje) e elegibilidade na data
 * da sessao sao perguntas diferentes: oferecer e escolher exigem as duas.
 */
class GerenciadorVaga implements GerirVagaEscala
{
    public const MOTIVO_TROCA_VAGA_PREENCHIDA = 'vaga preenchida';

    private const STATUS_SESSAO_ACEITOS = ['prevista', 'aberta'];

    public function __construct(
        private readonly TravaEscala $trava,
        private readonly DonaDaEscala $dona,
        private readonly ConsultarEquipeEbi $equipe,
        private readonly ConsultarSessao $sessoes,
        private readonly EncerradorVaga $encerrador,
        private readonly CancelamentoTroca $trocas,
        private readonly DetectorConflito $conflitos,
        private readonly AvaliadorEquipe $avaliador,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function abrir(string $escalaId, ?string $substituiMembroId, ?string $observacao, ContextoDecisao $contexto, ?string $autorId): string
    {
        return $this->trava->executar($escalaId, function (Escala $escala) use ($substituiMembroId, $observacao, $contexto, $autorId): string {
            $this->dona->garantirGerir((string) $escala->id, $contexto);

            $donaId = $this->dona->donaId((string) $escala->id);
            $vigentes = $this->equipe->papeisVigentesHoje([$donaId], (string) $escala->ebi_id, (string) $escala->igreja_id)[$donaId] ?? [];

            if ($donaId === '' || array_intersect($vigentes, EscalaMembro::PAPEIS_POR_FUNCAO[EscalaMembro::FUNCAO_COORDENADORA]) === []) {
                throw new ProblemException('dona_sem_vinculo', 'Dona sem vínculo', 409, 'A coordenadora dona desta escala não tem vínculo vigente; ninguém poderia escolher a oferta.');
            }

            $sessao = $this->sessaoEditavel((string) $escala->sessao_id);
            $substituido = null;

            if ($substituiMembroId !== null && $substituiMembroId !== '') {
                $substituido = $escala->membros()->whereKey($substituiMembroId)->first();

                if ($substituido === null
                    || $substituido->status === EscalaMembro::STATUS_SUBSTITUIDO
                    || $substituido->funcao !== EscalaMembro::FUNCAO_AUXILIAR) {
                    throw new ProblemException('membro_invalido', 'Membro inválido', 422, 'Só é possível substituir um auxiliar ativo desta escala.');
                }

                $aberta = VagaEscala::query()
                    ->where('substitui_membro_id', $substituido->id)
                    ->where('status', VagaEscala::STATUS_ABERTA)
                    ->exists();

                if ($aberta) {
                    throw new ProblemException('vaga_existente', 'Vaga existente', 409, 'Este membro já tem uma vaga aberta.');
                }
            }

            $vaga = VagaEscala::query()->create([
                'escala_id' => $escala->id,
                'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
                'substitui_membro_id' => $substituido?->id,
                'substituido_usuario_id' => $substituido?->usuario_id,
                'observacao' => ($observacao === null || trim($observacao) === '') ? null : trim($observacao),
                'status' => VagaEscala::STATUS_ABERTA,
                'aberta_por' => $autorId,
            ]);

            $this->auditoria->registrar([
                'acao' => 'escala.vaga_aberta',
                'recurso' => 'vaga_escala',
                'ator_id' => $autorId,
                'origem' => 'api',
                'escopo' => 'ebi:'.(string) $escala->ebi_id,
                'metadados_minimizados' => [
                    'vaga_id' => (string) $vaga->id,
                    'escala_id' => (string) $escala->id,
                    'tipo' => $substituido === null ? 'acrescimo' : 'substituicao',
                    'substitui_membro_id' => $substituido === null ? null : (string) $substituido->id,
                    'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
                ],
            ]);

            return (string) $vaga->id;
        });
    }

    public function cancelar(string $vagaId, ContextoDecisao $contexto, ?string $autorId): void
    {
        $vaga = $this->vaga($vagaId);

        $this->trava->executar((string) $vaga->escala_id, function () use ($vagaId, $contexto, $autorId): void {
            $this->dona->garantirGerir((string) $this->vaga($vagaId)->escala_id, $contexto);
            $vaga = $this->vaga($vagaId);

            if (! $vaga->estaAberta()) {
                throw new ProblemException('vaga_indisponivel', 'Vaga indisponível', 409, 'Esta vaga já foi preenchida ou cancelada.');
            }

            $this->encerrador->cancelar($vaga, VagaEscala::MOTIVO_GESTAO, $autorId);
        });
    }

    public function oferecer(string $vagaId, string $usuarioId): string
    {
        $vaga = $this->vaga($vagaId);

        return $this->trava->executar((string) $vaga->escala_id, function () use ($vagaId, $usuarioId): string {
            $vaga = $this->vaga($vagaId);
            $escala = Escala::query()->findOrFail($vaga->escala_id);
            $sessao = $this->sessao((string) $escala->sessao_id);

            // RF-2/RF-3: a oferta e de membro vigente da equipe do EBI hoje;
            // a vigencia na data da sessao e checada na escolha.
            $aceitos = EscalaMembro::PAPEIS_POR_FUNCAO[EscalaMembro::FUNCAO_AUXILIAR];
            $papeis = $this->equipe->papeisVigentesHoje([$usuarioId], $sessao->ebiId, $sessao->igrejaId)[$usuarioId] ?? [];

            if (array_intersect($papeis, $aceitos) === []) {
                throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vaga não encontrada.');
            }

            $this->garantirVagaAberta($vaga, $sessao);
            $this->garantirElegivelNaData($escala, $sessao, $usuarioId);

            if ($this->dona->donaId((string) $escala->id) === $usuarioId || in_array($usuarioId, $this->dona->usuariosNaEscala((string) $escala->id), true)) {
                throw new ProblemException('membro_ja_escalado', 'Membro já escalado', 422, 'Esta pessoa já está na escala desta sessão.');
            }

            $this->garantirSemConflito($escala, $sessao, $usuarioId);

            $pendente = OfertaVagaEscala::query()
                ->where('vaga_id', $vaga->id)
                ->where('usuario_id', $usuarioId)
                ->where('status', OfertaVagaEscala::STATUS_PENDENTE)
                ->exists();

            if ($pendente) {
                throw new ProblemException('oferta_existente', 'Oferta existente', 409, 'Você já se ofereceu para esta vaga.');
            }

            $oferta = OfertaVagaEscala::query()->create([
                'vaga_id' => $vaga->id,
                'usuario_id' => $usuarioId,
                'status' => OfertaVagaEscala::STATUS_PENDENTE,
            ]);

            $this->auditar('escala.vaga_oferta', $escala, [
                'vaga_id' => (string) $vaga->id,
                'oferta_id' => (string) $oferta->id,
                'escala_id' => (string) $escala->id,
            ], $usuarioId);

            return (string) $oferta->id;
        });
    }

    public function retirarOferta(string $ofertaId, string $usuarioId): void
    {
        $oferta = OfertaVagaEscala::query()->find($ofertaId);

        if ($oferta === null || (string) $oferta->usuario_id !== $usuarioId) {
            throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Oferta não encontrada.');
        }

        $vaga = $this->vaga((string) $oferta->vaga_id);

        $this->trava->executar((string) $vaga->escala_id, function () use ($ofertaId, $usuarioId): void {
            $oferta = OfertaVagaEscala::query()->findOrFail($ofertaId);

            if ($oferta->status !== OfertaVagaEscala::STATUS_PENDENTE) {
                throw new ProblemException('oferta_ja_decidida', 'Oferta já decidida', 409, 'Esta oferta já foi decidida.');
            }

            $oferta->forceFill(['status' => OfertaVagaEscala::STATUS_RETIRADA])->save();
            $vaga = $this->vaga((string) $oferta->vaga_id);

            $this->auditar('escala.vaga_oferta_retirada', $this->escalaDa((string) $vaga->escala_id), [
                'vaga_id' => (string) $vaga->id,
                'oferta_id' => (string) $oferta->id,
                'escala_id' => (string) $vaga->escala_id,
            ], $usuarioId);
        });
    }

    public function escolher(string $ofertaId, ContextoDecisao $contexto, ?string $autorId): string
    {
        $vagaDaOferta = $this->vagaDaOferta($ofertaId);

        /** @var ResultadoVaga $resultado */
        $resultado = $this->trava->executar((string) $vagaDaOferta->escala_id, function (Escala $escala) use ($ofertaId, $contexto, $autorId): ResultadoVaga {
            $this->dona->garantirEscolher((string) $escala->id, $contexto);

            $oferta = OfertaVagaEscala::query()->findOrFail($ofertaId);

            if ($oferta->status !== OfertaVagaEscala::STATUS_PENDENTE) {
                throw new ProblemException('oferta_ja_decidida', 'Oferta já decidida', 409, 'Esta oferta já foi decidida.');
            }

            $vaga = $this->vaga((string) $oferta->vaga_id);
            $sessao = $this->sessao((string) $escala->sessao_id);
            $original = $vaga->substitui_membro_id === null ? null : $escala->membros()->whereKey($vaga->substitui_membro_id)->first();

            // Decisao 5.3: o original sumiu ou mudou de funcao; a vaga morre aqui
            // e o 409 sai depois do commit (estado persistido: cancelada).
            if ($vaga->substitui_membro_id !== null
                && ($original === null
                    || $original->status === EscalaMembro::STATUS_SUBSTITUIDO
                    || $original->funcao !== EscalaMembro::FUNCAO_AUXILIAR)) {
                $this->encerrador->cancelar($vaga, VagaEscala::MOTIVO_INDISPONIVEL, $autorId);

                return ResultadoVaga::indisponivel();
            }

            if (! $vaga->estaAberta()) {
                throw new ProblemException('vaga_indisponivel', 'Vaga indisponível', 409, 'Esta vaga já foi preenchida ou cancelada.');
            }

            $this->garantirSessaoAberta($sessao);

            $usuarioId = (string) $oferta->usuario_id;
            $this->garantirElegivelNaData($escala, $sessao, $usuarioId);

            if ($this->dona->donaId((string) $escala->id) === $usuarioId || in_array($usuarioId, $this->dona->usuariosNaEscala((string) $escala->id), true)) {
                throw new ProblemException('membro_ja_escalado', 'Membro já escalado', 422, 'Esta pessoa já está na escala desta sessão.');
            }

            $this->garantirSemConflito($escala, $sessao, $usuarioId);

            if ($original !== null) {
                $original->forceFill(['status' => EscalaMembro::STATUS_SUBSTITUIDO])->save();
                $this->trocas->cancelarPendentes((string) $escala->id, (string) $original->id, self::MOTIVO_TROCA_VAGA_PREENCHIDA, $autorId);
            }

            $criado = EscalaMembro::query()->create([
                'escala_id' => $escala->id,
                'usuario_id' => $usuarioId,
                'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
                'status' => EscalaMembro::STATUS_CONFIRMADO,
                'confirmado_em' => now(),
                'substitui_membro_id' => $original?->id,
            ]);

            $vaga->forceFill([
                'status' => VagaEscala::STATUS_PREENCHIDA,
                'encerrada_por' => $autorId,
                'encerrada_em' => now(),
            ])->save();

            $oferta->forceFill(['status' => OfertaVagaEscala::STATUS_ACEITA, 'membro_criado_id' => $criado->id])->save();

            OfertaVagaEscala::query()
                ->where('vaga_id', $vaga->id)
                ->where('status', OfertaVagaEscala::STATUS_PENDENTE)
                ->update(['status' => OfertaVagaEscala::STATUS_DESCARTADA, 'updated_at' => now()]);

            $this->auditar('escala.vaga_preenchida', $escala, [
                'vaga_id' => (string) $vaga->id,
                'oferta_id' => (string) $oferta->id,
                'escala_id' => (string) $escala->id,
                'membro_criado_id' => (string) $criado->id,
                'substituido_usuario_id' => $vaga->substituido_usuario_id === null ? null : (string) $vaga->substituido_usuario_id,
            ], $autorId);

            return ResultadoVaga::ok((string) $criado->id);
        });

        if ($resultado->indisponivel) {
            throw new ProblemException('vaga_indisponivel', 'Vaga indisponível', 409, 'O membro substituído não está mais disponível nesta escala; a vaga foi cancelada.');
        }

        return (string) $resultado->id;
    }

    private function vaga(string $vagaId): VagaEscala
    {
        return VagaEscala::query()->find($vagaId)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Vaga não encontrada.');
    }

    private function vagaDaOferta(string $ofertaId): VagaEscala
    {
        $vagaId = (string) OfertaVagaEscala::query()->whereKey($ofertaId)->value('vaga_id');

        return $this->vaga($vagaId);
    }

    private function escalaDa(string $escalaId): Escala
    {
        return Escala::query()->findOrFail($escalaId);
    }

    private function sessao(string $sessaoId): IntervaloSessao
    {
        return $this->sessoes->intervalos([$sessaoId])[$sessaoId]
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Sessão não encontrada.');
    }

    private function sessaoEditavel(string $sessaoId): IntervaloSessao
    {
        $sessao = $this->sessao($sessaoId);
        $this->garantirSessaoAberta($sessao);

        return $sessao;
    }

    private function garantirSessaoAberta(IntervaloSessao $sessao): void
    {
        if (! in_array($sessao->status, self::STATUS_SESSAO_ACEITOS, true)) {
            throw new ProblemException('sessao_em_estado_invalido', 'Sessão em estado inválido', 409, 'A sessão precisa estar prevista ou aberta.');
        }
    }

    /** Vaga aberta e sessao utilizavel; senao 409 `vaga_indisponivel`. */
    private function garantirVagaAberta(VagaEscala $vaga, IntervaloSessao $sessao): void
    {
        if (! $vaga->estaAberta() || ! in_array($sessao->status, self::STATUS_SESSAO_ACEITOS, true)) {
            throw new ProblemException('vaga_indisponivel', 'Vaga indisponível', 409, 'Esta vaga não está mais disponível.');
        }
    }

    /** Elegibilidade agora (autorizacao atual) e na data da sessao (RF-3). */
    private function garantirElegivelNaData(Escala $escala, IntervaloSessao $sessao, string $usuarioId): void
    {
        $vigencias = $this->equipe->vigencias([$usuarioId], $sessao->ebiId, $sessao->igrejaId)[$usuarioId] ?? [];
        $papeis = $this->equipe->papeisVigentesHoje([$usuarioId], (string) $escala->ebi_id, (string) $escala->igreja_id)[$usuarioId] ?? [];

        if (array_intersect($papeis, EscalaMembro::PAPEIS_POR_FUNCAO[EscalaMembro::FUNCAO_AUXILIAR]) === []) {
            throw new ProblemException('membro_inelegivel', 'Membro inelegível', 422, 'Esta pessoa não pertence à equipe deste EBI.');
        }

        if (! $this->avaliador->vigenteEm($vigencias, $sessao->dataLocal, EscalaMembro::FUNCAO_AUXILIAR)) {
            throw new ProblemException('membro_inelegivel', 'Membro inelegível', 422, 'O vínculo desta pessoa não está vigente na data da sessão.');
        }
    }

    private function garantirSemConflito(Escala $escala, IntervaloSessao $sessao, string $usuarioId): void
    {
        $conflitos = $this->conflitos->conflitos([$usuarioId], $sessao, (string) $escala->id)[$usuarioId] ?? [];

        if ($conflitos !== []) {
            throw new ProblemException(
                'conflito_de_horario',
                'Conflito de horário',
                422,
                'Esta pessoa já está escalada em outra sessão no mesmo horário.',
                ['conflitos' => $this->conflitos->apresentar($conflitos, true)],
            );
        }
    }

    /** @param  array<string, mixed>  $metadados */
    private function auditar(string $acao, Escala $escala, array $metadados, ?string $autorId): void
    {
        $this->auditoria->registrar([
            'acao' => $acao,
            'recurso' => 'vaga_escala',
            'ator_id' => $autorId,
            'origem' => 'api',
            'escopo' => 'ebi:'.(string) $escala->ebi_id,
            'metadados_minimizados' => $metadados,
        ]);
    }
}
