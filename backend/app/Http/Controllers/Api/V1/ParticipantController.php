<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Participantes\Contracts\GerirParticipante;
use App\Http\Requests\Participantes\CadastrarAssistidoRequest;
use App\Http\Requests\Participantes\CadastrarParticipanteRequest;
use App\Http\Resources\ParticipanteResource;
use App\Support\Arquivo\Contracts\ArmazenarArquivo;
use App\Support\Participantes\AutorizacaoParticipante;
use App\Support\ProblemException;
use App\Support\Relatorios\VisibilidadeIgreja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController
{
    public function __construct(
        private readonly GerirParticipante $participantes,
        private readonly ArmazenarArquivo $arquivos,
        private readonly RegistrarEvento $auditoria,
        private readonly AutorizacaoParticipante $autorizacao,
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

        // H3: o resultado so inclui o que o escopo do ator cobre (o dedup nao
        // pode revelar cadastros de terceiros fora do territorio).
        $contexto = $this->contexto($request);
        $usuarioId = $this->usuarioId($request);
        $candidatos = array_values(array_filter(
            $candidatos,
            fn ($p): bool => $this->autorizacao->pode($contexto, $usuarioId, $p, 'participante.ler'),
        ));

        // Decisao 11 da spec: acima da igreja, um grupo de 1 a 4 ativos por
        // igreja de referencia ja deduz o cadastro. O corte roda sobre os
        // candidatos JA autorizados: senao um grupo fora do territorio
        // revelaria pela sinalizacao `suprimido` que ele existe.
        [$candidatos, $suprimido] = $this->participantes->cortarVisiveis(
            $candidatos,
            VisibilidadeIgreja::suprime($contexto),
        );

        $this->auditarLeitura($request, $finalidade, array_map(
            fn ($p): string => (string) $p->id,
            $candidatos,
        ));

        $corpo = ['data' => ParticipanteResource::collection($candidatos)->resolve($request)];

        if ($suprimido) {
            $corpo['suprimido'] = true;
            $corpo['motivo'] = 'limiar';
        }

        return response()->json($corpo);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        // Leitura por operador: a policy cobre `participante.ler` (territorial).
        // O caminho do responsavel (dependentes) e tratado em `/me/participants`.
        $finalidade = $this->finalidade($request);

        $participante = $this->participantes->encontrar($id)
            ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Participante não encontrado.');

        // H3: a leitura territorial precisa do recurso-alvo (igreja de
        // referencia). O responsavel com vinculo aprovado tambem passa
        // (propriedade, CA-04).
        $this->autorizacao->exigir(
            $this->contexto($request),
            $this->usuarioId($request),
            $participante,
            'participante.ler',
        );

        $this->auditarLeitura($request, $finalidade, [(string) $participante->id]);

        return (new ParticipanteResource($participante))->response();
    }

    private function contexto(Request $request): ?ContextoAtuacao
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        return $contexto instanceof ContextoAtuacao ? $contexto : null;
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
