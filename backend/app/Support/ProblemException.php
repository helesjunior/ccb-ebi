<?php

namespace App\Support;

use RuntimeException;

class ProblemException extends RuntimeException
{
    /**
     * @param  string  $problemCode  Codigo estavel do problema (campo `code`).
     * @param  string  $title  Resumo generico e estavel (RFC 9457).
     * @param  int  $status  Codigo HTTP.
     * @param  string|null  $detail  Explicacao especifica da ocorrencia.
     */
    public function __construct(
        public readonly string $problemCode,
        public readonly string $title,
        public readonly int $status,
        public readonly ?string $detail = null,
        /** @var array<string, mixed> */
        public readonly array $extra = [],
        public readonly string $type = 'about:blank',
    ) {
        parent::__construct($detail ?? $title);
    }

    /**
     * @return array<string, mixed>
     */
    public function toProblemArray(): array
    {
        return array_merge([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            'code' => $this->problemCode,
        ], $this->extra);
    }
}
