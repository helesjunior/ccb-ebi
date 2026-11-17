<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sessoes\Contracts\GerarSessoesPrevistas;
use App\Http\Requests\Agenda\GerarSessoesRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SessaoController
{
    public function __construct(private readonly GerarSessoesPrevistas $gerador) {}

    public function gerar(GerarSessoesRequest $request, string $id): JsonResponse
    {
        $dados = $request->validated();
        $de = $dados['de'] ?? CarbonImmutable::today()->toDateString();
        $ate = $dados['ate'] ?? CarbonImmutable::createFromFormat('!Y-m-d', $de)->addWeeks((int) ($dados['semanas'] ?? 4))->toDateString();

        return response()->json($this->gerador->gerar($id, $de, $ate), 201);
    }
}
