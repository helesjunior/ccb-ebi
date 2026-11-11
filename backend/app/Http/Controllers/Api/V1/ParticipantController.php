<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Http\Requests\Participantes\CadastrarAssistidoRequest;
use App\Http\Requests\Participantes\CadastrarParticipanteRequest;
use App\Http\Resources\ParticipanteResource;
use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController
{
    public function __construct(
        private readonly GerirParticipante $participantes,
        private readonly ArmazenarArquivo $arquivos,
        private readonly RegistrarEvento $auditoria,
    ) {}

    public function store(CadastrarParticipanteRequest $request): JsonResponse
    {
        $participante = $this->participantes->cadastrar(
            $this->usuarioId($request),
            $request->validated(),
        );

        return (new ParticipanteResource($participante))->response()->setStatusCode(201);
    }

    /** Cadastro assistido (FL-01): dados + evidencia obrigatoria (arquivo). */
    public function storeAssisted(CadastrarAssistidoRequest $request): JsonResponse
    {
        $arquivo = $this->arquivos->enfileirar(
            $request->file('evidencia'),
            'evidencia_consentimento',
            $this->usuarioId($request),
        );

        $dados = $request->safe()->except('evidencia');

        $participante = $this->participantes->cadastrarAssistido(
            $this->usuarioId($request),
            $dados,
            (string) $arquivo->id,
        );

        return (new ParticipanteResource($participante))->response()->setStatusCode(201);
    }

    /** Busca candidatos por nome normalizado + data (dedup, CA-27). */
    public function index(Request $request): JsonResponse
    {
        $finalidade = $this->finalidade($request);

        $candidatos = $this->participantes->buscar(
            (string) $request->query('q', ''),
            $request->query('data_nascimento'),
            $request->query('igreja_referencia_id'),
        );

        $this->auditarLeitura($request, $finalidade, array_map(
            fn ($p): string => (string) $p->id,
            $candidatos,
        ));

        return response()->json([
            'data' => ParticipanteResource::collection($candidatos)->resolve($request),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        // Leitura por operador: a policy cobre `participante.ler` (territorial).
        // O caminho do responsavel (dependentes) e tratado em `/me/participants`.
        $finalidade = $this->finalidade($request);

        $participante = $this->participantes->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        $this->auditarLeitura($request, $finalidade, [(string) $participante->id]);

        return (new ParticipanteResource($participante))->response();
    }

    private function usuarioId(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? '');
    }

    /**
     * Leitura identificavel exige finalidade declarada (matriz `*`): sem ela, a
     * leitura e recusada. Nao reusa a finalidade do termo de consentimento.
     */
    private function finalidade(Request $request): string
    {
        $finalidade = trim((string) ($request->query('finalidade', $request->header('X-Finalidade', ''))));

        if ($finalidade === '') {
            throw new ProblemException(
                'finalidade_obrigatoria',
                'Dados inválidos',
                422,
                'A leitura de dado identificável exige a finalidade declarada.',
            );
        }

        return $finalidade;
    }

    /**
     * @param  array<int, string>  $participanteIds
     */
    private function auditarLeitura(Request $request, string $finalidade, array $participanteIds): void
    {
        $this->auditoria->registrar([
            'acao' => 'participante.lido',
            'recurso' => 'participante',
            'ator_id' => $this->usuarioId($request),
            'origem' => 'api',
            'escopo' => null,
            'motivo' => $finalidade,
            'metadados_minimizados' => ['participante_ids' => $participanteIds],
            'ocorrido_em' => now(),
        ]);
    }
}
