<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\GerirSessaoEbi;
use App\Domain\Sessoes\Contracts\SessaoEbi;
use App\Domain\Sessoes\Models\Sessao;
use App\Domain\Sessoes\Models\SessaoCorrecao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GerenciadorSessao implements GerirSessaoEbi
{
    public function __construct(
        private readonly ConsultarSessao $consulta,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function abrir(string $sessaoId, string $autorId): SessaoEbi
    {
        return DB::transaction(function () use ($sessaoId, $autorId): SessaoEbi {
            $sessao = $this->bloquear($sessaoId);
            $this->exigirEstado($sessao, Sessao::STATUS_PREVISTA);
            $sessao->fill(['status' => Sessao::STATUS_ABERTA, 'inicio_real' => now()]);
            $this->validarInstantes($sessao);
            $sessao->save();
            $this->auditar($sessao, 'sessao.aberta', $autorId);

            return $this->consulta->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404);
        });
    }

    public function encerrar(string $sessaoId, bool $realizada, ?string $fimRealIso, string $autorId): SessaoEbi
    {
        return DB::transaction(function () use ($sessaoId, $realizada, $fimRealIso, $autorId): SessaoEbi {
            $sessao = $this->bloquear($sessaoId);
            $this->exigirEstado($sessao, Sessao::STATUS_ABERTA);
            Validator::make(['fim_real' => $fimRealIso], ['fim_real' => ['nullable', 'date']])->validate();
            $sessao->fill(['status' => Sessao::STATUS_ENCERRADA, 'realizada' => $realizada,
                'fim_real' => $fimRealIso ? CarbonImmutable::parse($fimRealIso)->utc() : now()]);
            $this->validarInstantes($sessao);
            $sessao->save();
            $this->auditar($sessao, 'sessao.encerrada', $autorId, metadados: ['realizada' => $realizada]);

            return $this->consulta->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404);
        });
    }

    public function cancelar(string $sessaoId, string $motivo, string $autorId): SessaoEbi
    {
        return DB::transaction(function () use ($sessaoId, $motivo, $autorId): SessaoEbi {
            $sessao = $this->bloquear($sessaoId);
            $this->exigirEstado($sessao, Sessao::STATUS_PREVISTA);
            Validator::make(compact('motivo'), ['motivo' => ['required', 'string', 'min:3', 'max:500']])->validate();
            $sessao->update(['status' => Sessao::STATUS_CANCELADA, 'motivo_cancelamento' => $motivo, 'cancelada_por' => $autorId]);
            $this->auditar($sessao, 'sessao.cancelada', $autorId, $motivo);

            return $this->consulta->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404);
        });
    }

    public function corrigir(string $sessaoId, array $dados, string $autorId): SessaoEbi
    {
        return DB::transaction(function () use ($sessaoId, $dados, $autorId): SessaoEbi {
            $sessao = $this->bloquear($sessaoId);
            if ($sessao->status === Sessao::STATUS_CANCELADA) {
                throw new ProblemException('sessao_em_estado_invalido', 'Conflito de estado', 409);
            }
            $motivo = $dados['motivo'] ?? null;
            if ($sessao->status === Sessao::STATUS_ENCERRADA && blank($motivo)) {
                throw new ProblemException('motivo_obrigatorio', 'Dados inválidos', 422, 'Informe o motivo da correção.');
            }
            Validator::make($dados, [
                'tema' => ['sometimes', 'nullable', 'string', 'max:255'],
                'passagem_biblica' => ['sometimes', 'nullable', 'string', 'max:255'],
                'inicio_real' => ['sometimes', 'nullable', 'date'],
                'fim_real' => ['sometimes', 'nullable', 'date'],
                'realizada' => ['sometimes', 'nullable', 'boolean'],
                'auxiliares_escalados' => ['sometimes', 'nullable', 'integer', 'min:0'],
                'motivo' => ['nullable', 'string', 'min:3', 'max:500'],
            ])->validate();
            $campos = ['tema', 'passagem_biblica', 'inicio_real', 'fim_real', 'realizada', 'auxiliares_escalados'];
            $original = $sessao->toArray();
            $alteracoes = Arr::only($dados, $campos);
            foreach (['inicio_real', 'fim_real'] as $campo) {
                if (isset($alteracoes[$campo])) {
                    $alteracoes[$campo] = CarbonImmutable::parse($alteracoes[$campo])->utc();
                }
            }
            $sessao->fill($alteracoes);
            $this->validarInstantes($sessao);
            $alterados = array_keys($sessao->getDirty());
            if ($alterados === []) {
                return $this->consulta->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
            }
            $antes = Arr::only($original, $alterados);
            $depois = Arr::only($sessao->toArray(), $alterados);
            $sessao->save();
            SessaoCorrecao::query()->create([
                'sessao_id' => $sessaoId, 'autor_id' => $autorId, 'motivo' => $motivo,
                'antes' => $antes, 'depois' => $depois, 'ocorrido_em' => now(),
            ]);
            $this->auditar($sessao, 'sessao.corrigida', $autorId, $motivo);

            return $this->consulta->detalhe($sessaoId) ?? throw new ProblemException('not_found', 'Recurso nao encontrado', 404);
        });
    }

    private function bloquear(string $id): Sessao
    {
        return Sessao::query()->lockForUpdate()->find($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);
    }

    private function exigirEstado(Sessao $sessao, string $estado): void
    {
        if ($sessao->status !== $estado) {
            throw new ProblemException('sessao_em_estado_invalido', 'Conflito de estado', 409, 'A sessão não permite esta transição.');
        }
    }

    private function validarInstantes(Sessao $sessao): void
    {
        if ($sessao->inicio_real !== null && $sessao->fim_real !== null && $sessao->fim_real->lt($sessao->inicio_real)) {
            throw new ProblemException('horario_invalido', 'Dados inválidos', 422, 'O fim real informado é anterior ao início da sessão.');
        }
    }

    /** @param array<string, mixed> $metadados */
    private function auditar(Sessao $sessao, string $acao, string $autorId, ?string $motivo = null, array $metadados = []): void
    {
        $this->auditoria->registrar([
            'acao' => $acao, 'recurso' => 'sessao', 'ator_id' => $autorId, 'origem' => 'api',
            'escopo' => "ebi:{$sessao->ebi_id}", 'motivo' => $motivo,
            'metadados_minimizados' => ['sessao_id' => (string) $sessao->id] + $metadados,
        ]);
    }
}
