<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Http\Resources\TermoVersaoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyTermController
{
    public function __construct(private readonly GerirTermos $termos) {}

    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'finalidade' => ['required', 'string'],
        ]);

        $termo = $this->termos->vigente($dados['finalidade']);

        return response()->json([
            'data' => TermoVersaoResource::collection($termo === null ? [] : [$termo])->resolve($request),
        ]);
    }
}
