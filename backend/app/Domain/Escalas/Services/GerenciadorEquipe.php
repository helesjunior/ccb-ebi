<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Escalas\Contracts\ConsultarEquipe;
use App\Domain\Escalas\Contracts\EquipeRef;
use App\Domain\Escalas\Contracts\GerirEquipe;
use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\EquipeMembro;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\EbiDados;
use App\Support\ProblemException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Escrita da equipe de uma coordenadora (E07, evolucao): criar, incluir e
 * retirar auxiliares. As regras de quem edita ficam na `PoliticaEquipe`.
 *
 * Diferente da escala, entrar na equipe nao depende dos papeis de escala: a
 * dona convida qualquer pessoa do EBI (ou da igreja dele) com vinculo vigente
 * hoje, e quem perdeu o vinculo continua no quadro, marcado sem vinculo na
 * leitura.
 */
class GerenciadorEquipe implements GerirEquipe
{
    /**
     * Papeis que a dona pode incluir na propria equipe. A equipe e feita de
     * auxiliares, mas uma coordenadora pode entrar como auxiliar de outra
     * equipe e por isso aparece aqui.
     */
    private const PAPEIS_INCLUIVEIS = ['auxiliar', 'coordenadora'];

    public function __construct(
        private readonly ConsultarEbi $ebis,
        private readonly ConsultarEquipe $consulta,
        private readonly ConsultarEquipeEbi $vinculos,
        private readonly PoliticaEquipe $politica,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function criar(string $ebiId, string $coordenadoraId, ContextoDecisao $contexto, ?string $autorId): Equipe
    {
        $ebi = $this->ebi($ebiId);
        $this->politica->garantirPodeCriar($contexto, $coordenadoraId);

        $papeis = $this->vinculos->papeisVigentesHoje([$coordenadoraId], $ebi->id, $ebi->igrejaId)[$coordenadoraId] ?? [];
        if (! in_array('coordenadora', $papeis, true)) {
            throw new ProblemException(
                'coordenadora_invalida',
                'Coordenadora sem vinculo',
                422,
                'A pessoa escolhida nao e coordenadora vigente neste EBI.',
            );
        }

        try {
            return DB::transaction(function () use ($ebi, $coordenadoraId, $autorId): Equipe {
                $equipe = Equipe::query()->create([
                    'ebi_id' => $ebi->id,
                    'igreja_id' => $ebi->igrejaId,
                    'coordenadora_id' => $coordenadoraId,
                    'criado_por' => $autorId,
                ]);

                $this->auditoria->registrar([
                    'acao' => 'equipe.criada',
                    'recurso' => 'equipe',
                    'ator_id' => $autorId,
                    'origem' => 'api',
                    'escopo' => 'ebi:'.$ebi->id,
                    'metadados_minimizados' => [
                        'equipe_id' => (string) $equipe->id,
                        'ebi_id' => $ebi->id,
                        'coordenadora_id' => $coordenadoraId,
                    ],
                ]);

                return $this->montar($equipe->id);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ProblemException('equipe_existente', 'Equipe ja existe', 409, 'Esta coordenadora ja tem equipe neste EBI.');
        }
    }

    public function incluirMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): Equipe
    {
        $equipe = $this->equipeExistente($equipeId);
        $this->politica->garantirDona($contexto, $equipe);

        $papeis = $this->vinculos->papeisVigentesHoje([$usuarioId], $equipe->ebiId, $equipe->igrejaId)[$usuarioId] ?? [];
        if (array_intersect($papeis, self::PAPEIS_INCLUIVEIS) === []) {
            throw new ProblemException(
                'membro_inelegivel',
                'Pessoa inelegivel para a equipe',
                422,
                'So entra na equipe quem tem vinculo de auxiliar ou coordenadora vigente neste EBI.',
            );
        }

        try {
            DB::transaction(function () use ($equipe, $usuarioId, $autorId): void {
                EquipeMembro::query()->create([
                    'equipe_id' => $equipe->id,
                    'usuario_id' => $usuarioId,
                    'incluido_por' => $autorId,
                ]);

                $this->auditoria->registrar([
                    'acao' => 'equipe.membro_incluido',
                    'recurso' => 'equipe',
                    'ator_id' => $autorId,
                    'origem' => 'api',
                    'escopo' => 'ebi:'.$equipe->ebiId,
                    'metadados_minimizados' => [
                        'equipe_id' => $equipe->id,
                        'ebi_id' => $equipe->ebiId,
                        'coordenadora_id' => $equipe->coordenadoraId,
                        'usuario_id' => $usuarioId,
                    ],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new ProblemException('membro_ja_na_equipe', 'Membro ja na equipe', 409, 'Esta pessoa ja esta na equipe.');
        }

        return $this->montar($equipe->id);
    }

    public function retirarMembro(string $equipeId, string $usuarioId, ContextoDecisao $contexto, ?string $autorId): Equipe
    {
        $equipe = $this->equipeExistente($equipeId);
        $this->politica->garantirDona($contexto, $equipe);

        $retirado = DB::transaction(function () use ($equipe, $usuarioId, $autorId): bool {
            $removidos = EquipeMembro::query()
                ->where('equipe_id', $equipe->id)
                ->where('usuario_id', $usuarioId)
                ->delete();

            if ($removidos === 0) {
                return false;
            }

            $this->auditoria->registrar([
                'acao' => 'equipe.membro_retirado',
                'recurso' => 'equipe',
                'ator_id' => $autorId,
                'origem' => 'api',
                'escopo' => 'ebi:'.$equipe->ebiId,
                'metadados_minimizados' => [
                    'equipe_id' => $equipe->id,
                    'ebi_id' => $equipe->ebiId,
                    'coordenadora_id' => $equipe->coordenadoraId,
                    'usuario_id' => $usuarioId,
                ],
            ]);

            return true;
        });

        if (! $retirado) {
            throw new ProblemException('not_found', 'Recurso nao encontrado', 404, 'Esta pessoa nao esta na equipe.');
        }

        return $this->montar($equipe->id);
    }

    private function equipeExistente(string $equipeId): EquipeRef
    {
        return $this->consulta->referencia($equipeId)
            ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404, 'Equipe nao encontrada.');
    }

    /** Detalhe da equipe como o recurso devolve (mesma visao da leitura). */
    private function montar(string $equipeId): Equipe
    {
        $detalhe = $this->consulta->detalhe($equipeId)
            ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404, 'Equipe nao encontrada.');

        $equipe = Equipe::query()->findOrFail($equipeId);
        $equipe->setAttribute('detalhe', $detalhe);

        return $equipe;
    }

    private function ebi(string $ebiId): EbiDados
    {
        return $this->ebis->dados($ebiId)
            ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404, 'EBI nao encontrada.');
    }
}
