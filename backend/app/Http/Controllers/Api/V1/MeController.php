<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Contracts\GerirPerfil;
use App\Domain\Identidade\Services\ContextoAtuacao;
use App\Domain\Organizacao\Contracts\ConsultarEbi;
use App\Http\Requests\Identidade\PerfilAtualizarRequest;
use App\Http\Requests\Identidade\PerfilSenhaRequest;
use App\Http\Resources\ContextoAtuacaoResource;
use App\Http\Resources\EbiResumoResource;
use App\Http\Resources\UsuarioResource;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MeController
{
    public function __construct(
        private readonly ConsultarVinculos $vinculos,
        private readonly ExigirMfa $mfa,
        private readonly GerirPerfil $gerirPerfil,
        private readonly ConsultarEbi $ebis,
    ) {}

    public function me(Request $request): UsuarioResource
    {
        $usuario = $this->usuario($request);
        $usuario->loadMissing('vinculosVigentes.papel');
        $usuario->setAttribute('mfa_ativo', $this->mfa->temFatorValidado($usuario));

        return UsuarioResource::make($usuario);
    }

    public function atualizarPerfil(PerfilAtualizarRequest $request): UsuarioResource
    {
        $usuario = $this->usuario($request);
        $this->gerirPerfil->atualizar($usuario, $request->validated());
        $usuario->setAttribute('mfa_ativo', $this->mfa->temFatorValidado($usuario));

        return UsuarioResource::make($usuario);
    }

    public function alterarSenha(PerfilSenhaRequest $request): Response
    {
        $this->gerirPerfil->alterarSenha(
            $this->usuario($request),
            $request->string('senha_atual')->toString(),
            $request->string('nova_senha')->toString(),
        );

        return response()->noContent();
    }

    public function scopes(Request $request): JsonResponse
    {
        $escopos = $this->vinculos->vigentesDoUsuario($this->usuario($request));

        return new JsonResponse([
            'data' => ContextoAtuacaoResource::collection($escopos)->resolve($request),
        ]);
    }

    /**
     * Seletor de EBIs alcancadas pelo ator (RF-06/E04): quando existe contexto
     * de atuacao na request, a lista usa apenas aquele no; senao, todos os
     * vinculos vigentes. Sem Gate — o recorte e o proprio vinculo.
     */
    public function ebis(Request $request): JsonResponse
    {
        $contexto = $request->attributes->get('contexto_atuacao');

        /** @var array<int, array{tipo_escopo: string, escopo_id: string}> $alvos */
        $alvos = $contexto instanceof ContextoAtuacao
            ? [['tipo_escopo' => $contexto->tipoEscopo, 'escopo_id' => $contexto->escopoId]]
            : $this->vinculos->vigentesDoUsuario($this->usuario($request));

        $porId = [];
        foreach ($alvos as $alvo) {
            foreach ($this->ebis->listarPorEscopo($alvo['tipo_escopo'], $alvo['escopo_id']) as $ebi) {
                $porId[$ebi->id] ??= $ebi;
            }
        }

        $ordenadas = collect($porId)->sortBy('nome', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return new JsonResponse([
            'data' => EbiResumoResource::collection($ordenadas)->resolve($request),
        ]);
    }

    private function usuario(Request $request): Authenticatable
    {
        $usuario = $request->user();

        if ($usuario === null) {
            throw new ProblemException('unauthorized', 'Não autenticado', 401);
        }

        return $usuario;
    }
}
