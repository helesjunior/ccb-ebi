<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ProblemDetails
{
    public static function from(ProblemException $e): JsonResponse
    {
        return new JsonResponse(
            $e->toProblemArray(),
            $e->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
