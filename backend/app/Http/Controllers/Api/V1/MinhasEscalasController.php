<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Escalas\Contracts\ConsultarEscala;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Minhas escalas" (RF-07): as vagas da propria pessoa no periodo, com os
 * proprios conflitos. Nao exige `escala.ler`: o recorte e sempre o do usuario
 * autenticado.
 */
class MinhasEscalasController
{
    /** Mesmo teto das listagens de sessao. */
    private const DIAS_MAXIMOS = 92;

    public function __construct(private readonly ConsultarEscala $consulta) {}

    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
        ]);

        $inicio = CarbonImmutable::parse($dados['inicio'], 'America/Sao_Paulo');
        $fim = CarbonImmutable::parse($dados['fim'], 'America/Sao_Paulo');

        if ($fim->gt($inicio->addDays(self::DIAS_MAXIMOS))) {
            throw ValidationException::withMessages([
                'fim' => 'O intervalo não pode exceder '.self::DIAS_MAXIMOS.' dias.',
            ]);
        }

        return new JsonResponse(['data' => $this->consulta->minhas(
            (string) $request->user()?->getAuthIdentifier(),
            $inicio->utc()->toDateTimeString(),
            $fim->addDay()->utc()->toDateTimeString(),
        )]);
    }
}
