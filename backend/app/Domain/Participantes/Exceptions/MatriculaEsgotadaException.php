<?php

namespace App\Domain\Participantes\Exceptions;

use RuntimeException;

/**
 * Esgotaram-se as tentativas de gerar uma matricula livre (colisao repetida).
 * Praticamente impossivel com 40 bits; existe para falhar alto e claro.
 */
class MatriculaEsgotadaException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não foi possível gerar uma matrícula livre após várias tentativas.');
    }
}
