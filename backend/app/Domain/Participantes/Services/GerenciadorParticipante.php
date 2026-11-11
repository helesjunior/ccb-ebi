<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    public function __construct(
        private readonly RegistrarEvento $auditoria,
        private readonly DetectorDuplicidade $detector,
        private readonly RegistrarConsentimento $consentimentos,
        private readonly GerirTermos $termos,
    ) {}

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

    public function buscar(string $nome, ?string $dataNascimento = null, ?string $igrejaReferenciaId = null): array
    {
        return $this->detector->detectar([
            'nome' => $nome,
            'data_nascimento' => $dataNascimento,
            'igreja_referencia_id' => $igrejaReferenciaId,
        ]);
    }

    public function cadastrarAssistido(string $usuarioId, array $dados, string $evidenciaArquivoId): Participante
    {
        if ($evidenciaArquivoId === '') {
            throw new ProblemException('evidencia_obrigatoria', 'Dados inválidos', 422, 'A evidência do cadastro assistido é obrigatória.');
        }

        $this->validarNascimento($dados['data_nascimento'] ?? null);

        $termo = $this->termos->vigente('cadastro');

        if ($termo === null) {
            throw new ProblemException('termo_indisponivel', 'Termo indisponível', 422, 'Não há termo de cadastro vigente.');
        }

        return DB::transaction(function () use ($usuarioId, $dados, $evidenciaArquivoId, $termo): Participante {
            $participante = Participante::criarComMatricula([
                'nome' => (string) $dados['nome'],
                'nome_social' => $dados['nome_social'] ?? null,
                'data_nascimento' => $dados['data_nascimento'],
                'status' => Participante::STATUS_ATIVO,
                'igreja_referencia_id' => $dados['igreja_referencia_id'] ?? null,
                'origem_cadastro' => Participante::ORIGEM_ASSISTIDO,
                'cadastrado_por' => $usuarioId,
                'evidencia_consentimento_id' => $evidenciaArquivoId,
            ]);

            // Responsavel sem conta (cadastro assistido): o vinculo nasce
            // aprovado, pois foi a propria coordenadora que cadastrou.
            ResponsavelParticipante::create([
                'usuario_id' => null,
                'nome' => (string) ($dados['responsavel_nome'] ?? $dados['nome']),
                'telefone' => $dados['responsavel_telefone'] ?? null,
                'participante_id' => $participante->id,
                'tipo_relacao' => (string) ($dados['tipo_relacao'] ?? 'responsavel'),
                'permissoes' => [],
                'status' => 'ativo',
                'validacao' => ResponsavelParticipante::VALIDACAO_APROVADA,
                'conferido_em' => now(),
                'conferido_por' => $usuarioId,
                'inicio' => now()->toDateString(),
            ]);

            // Consentimento do cadastro assistido: usuario nulo, evidencia com
            // o arquivo, referenciando o participante (CA-16).
            $this->consentimentos->registrar(
                null,
                (string) $termo->id,
                'aceito',
                'assistido',
                new EvidenciaAceite(
                    ip: 'presencial',
                    userAgent: 'cadastro-assistido',
                    termoHash: $termo->texto_hash ?? null,
                    arquivoId: $evidenciaArquivoId,
                ),
                $participante->id,
            );

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
                    'evidencia_id' => $evidenciaArquivoId,
                ],
                'ocorrido_em' => now(),
            ]);

            return $participante;
        });
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
