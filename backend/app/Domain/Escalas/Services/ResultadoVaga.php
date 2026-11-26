<?php

namespace App\Domain\Escalas\Services;

/**
 * O que a `TravaEscala` gravou antes de recusar. A vaga pode precisar ser
 * encerrada e a chamada ainda responder 409; o servico devolve o resultado
 * de dentro da trava e lanca fora dela, depois do commit.
 */
final readonly class ResultadoVaga
{
    private function __construct(public ?string $id, public bool $indisponivel) {}

    public static function ok(string $id): self
    {
        return new self($id, false);
    }

    public static function indisponivel(): self
    {
        return new self(null, true);
    }
}
