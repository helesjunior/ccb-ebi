<?php

namespace App\Domain\Escalas\Contracts;

/**
 * O que o leitor da escala pode ver do conflito (CA-12). A camada HTTP decide,
 * com o contexto de atuacao em maos, e entrega a decisao pronta ao dominio: o
 * modulo Escalas nao conhece papeis nem escopos.
 */
final readonly class EscalaVisao
{
    /**
     * @param  ?string  $leitorId  quem le (a propria pessoa ve o proprio conflito)
     * @param  array<int, string>  $ebisVisiveis  outros EBIs sobre os quais o leitor tem `escala.ler`
     * @param  bool  $visaoAmpla  leitura interna (apos escrita): todos os nomes
     */
    public function __construct(
        public ?string $leitorId = null,
        public array $ebisVisiveis = [],
        public bool $visaoAmpla = false,
    ) {}

    /** Leitura interna (apos escrita): nomes livres. */
    public static function interna(?string $leitorId = null): self
    {
        return new self($leitorId, visaoAmpla: true);
    }
}
