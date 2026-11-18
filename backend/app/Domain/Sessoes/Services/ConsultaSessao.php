<?php

namespace App\Domain\Sessoes\Services;

use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Domain\Organizacao\Contracts\EbiDados;
use App\Domain\Sessoes\Contracts\ConsultarSessao;
use App\Domain\Sessoes\Contracts\SessaoEbi;
use App\Domain\Sessoes\Models\Sessao;
use App\Support\ProblemException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ConsultaSessao implements ConsultarSessao
{
    public function __construct(private readonly ConsultarEbi $ebis) {}

    public function listar(string $ebiId, string $de, string $ate, ?string $status = null): array
    {
        Validator::make(compact('de', 'ate', 'status'), [
            'de' => ['required', 'date_format:Y-m-d'],
            'ate' => ['required', 'date_format:Y-m-d', 'after_or_equal:de'],
            'status' => ['nullable', Rule::in(['prevista', 'aberta', 'encerrada', 'cancelada'])],
        ])->validate();
        $ebi = $this->ebis->dados($ebiId) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);

        $inicioLocal = CarbonImmutable::parse($de, $ebi->timezone)->startOfDay();
        $fimLocal = CarbonImmutable::parse($ate, $ebi->timezone)->startOfDay();
        if ($fimLocal->gt($inicioLocal->addDays(92))) {
            throw new ProblemException('intervalo_invalido', 'Dados inválidos', 422, 'O intervalo não pode exceder 92 dias.');
        }
        $inicio = $inicioLocal->utc();
        $fim = $fimLocal->addDay()->utc();

        return Sessao::query()->where('ebi_id', $ebiId)
            ->where('inicio_previsto', '>=', $inicio)->where('inicio_previsto', '<', $fim)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->withCount('presencasValidas as presentes')->orderBy('inicio_previsto')->get()
            ->map(fn (Sessao $sessao): SessaoEbi => $this->dto($sessao, $ebi))->all();
    }

    public function detalhe(string $sessaoId): ?SessaoEbi
    {
        $sessao = Sessao::query()->withCount('presencasValidas as presentes')->find($sessaoId);
        if ($sessao === null) {
            return null;
        }
        $ebi = $this->ebis->dados($sessao->ebi_id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);

        return $this->dto($sessao, $ebi);
    }

    private function dto(Sessao $sessao, EbiDados $ebi): SessaoEbi
    {
        return new SessaoEbi(
            (string) $sessao->id, $sessao->ebi_id, $ebi->nome, $ebi->timezone, $sessao->igreja_id, $sessao->agenda_ebi_id,
            $sessao->inicio_previsto->copy()->utc()->toIso8601String(), $sessao->fim_previsto->copy()->utc()->toIso8601String(),
            $sessao->inicio_real?->copy()->utc()->toIso8601String(), $sessao->fim_real?->copy()->utc()->toIso8601String(),
            $sessao->tema, $sessao->passagem_biblica, $sessao->status, $sessao->realizada,
            $sessao->motivo_cancelamento, $sessao->auxiliares_escalados, (int) $sessao->getAttribute('presentes'),
        );
    }
}
