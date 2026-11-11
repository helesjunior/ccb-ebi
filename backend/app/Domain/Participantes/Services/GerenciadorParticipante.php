<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Domain\Participantes\Models\Participante;
use App\Support\ProblemException;
use Illuminate\Support\Carbon;

/**
 * Cadastro e correcao de participantes pelo responsavel (RF-02, CA-01).
 *
 * Toda escrita gera evento de auditoria. A igreja de referencia e opcional e,
 * quando presente, nao e validada contra Organizacao aqui: a coordenadora
 * confere o vinculo no fluxo de vinculo (RF-03). O cadastro nao cria vinculo
 * de responsavel — quem cria o vinculo e o fluxo de `GerirVinculoResponsavel`.
 */
class GerenciadorParticipante implements GerirParticipante
{
    public function __construct(private readonly RegistrarEvento $auditoria) {}

    public function cadastrar(string $usuarioId, array $dados): Participante
    {
        $this->validarNascimento($dados['data_nascimento'] ?? null);

        $participante = Participante::criarComMatricula([
            'nome' => (string) $dados['nome'],
            'nome_social' => $dados['nome_social'] ?? null,
            'data_nascimento' => $dados['data_nascimento'],
            'status' => Participante::STATUS_ATIVO,
            'igreja_referencia_id' => $dados['igreja_referencia_id'] ?? null,
            'origem_cadastro' => Participante::ORIGEM_RESPONSAVEL,
            'cadastrado_por' => $usuarioId,
        ]);

        $this->auditoria->registrar([
            'acao' => 'participante.cadastrado',
            'recurso' => 'participante',
            'ator_id' => $usuarioId,
            'origem' => 'api',
            'escopo' => null,
            'motivo' => null,
            'metadados_minimizados' => [
                'participante_id' => (string) $participante->id,
                'origem_cadastro' => $participante->origem_cadastro,
            ],
            'ocorrido_em' => now(),
        ]);

        return $participante;
    }

    public function encontrar(string $id): ?Participante
    {
        return Participante::query()->find($id);
    }

    public function corrigir(string $usuarioId, string $id, array $dados): Participante
    {
        $participante = $this->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        if (array_key_exists('data_nascimento', $dados)) {
            $this->validarNascimento($dados['data_nascimento']);
        }

        $antes = $participante->only(['nome', 'nome_social', 'data_nascimento', 'igreja_referencia_id']);

        $participante->fill(array_intersect_key($dados, array_flip([
            'nome', 'nome_social', 'data_nascimento', 'igreja_referencia_id',
        ])))->save();

        $this->auditoria->registrar([
            'acao' => 'participante.corrigido',
            'recurso' => 'participante',
            'ator_id' => $usuarioId,
            'origem' => 'api',
            'escopo' => null,
            'motivo' => null,
            'metadados_minimizados' => [
                'participante_id' => (string) $participante->id,
                'campos' => array_keys($dados),
                // Estado anterior para reversao (ADR-0007): dado minimo, sem PII nova.
                'antes' => $antes,
            ],
            'ocorrido_em' => now(),
        ]);

        return $participante;
    }

    private function validarNascimento(mixed $data): void
    {
        if (! is_string($data) || $data === '') {
            throw new ProblemException('dados_invalidos', 'Dados inválidos', 422, 'Data de nascimento é obrigatória.');
        }

        if (Carbon::parse($data)->isAfter(now()->startOfDay())) {
            throw new ProblemException('data_futura', 'Dados inválidos', 422, 'A data de nascimento não pode ser futura.');
        }
    }
}
