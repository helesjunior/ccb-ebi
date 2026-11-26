<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Leitura identificavel exige finalidade declarada (matriz `*`, RNF-07): sem
 * ela, a leitura e recusada. Nao reusa a finalidade do termo de consentimento.
 * Vai para `evento_auditoria.motivo` (varchar 255).
 */
final class FinalidadeLeitura
{
    public static function exigir(Request $request): string
    {
        $finalidade = trim((string) $request->query('finalidade', $request->header('X-Finalidade', '')));

        if ($finalidade === '') {
            throw new ProblemException(
                'finalidade_obrigatoria',
                'Dados inválidos',
                422,
                'A leitura de dado identificável exige a finalidade declarada.',
            );
        }

        if (mb_strlen($finalidade) > 255) {
            throw new ProblemException('finalidade_invalida', 'Dados inválidos', 422, 'A finalidade deve ter até 255 caracteres.');
        }

        return $finalidade;
    }
}
