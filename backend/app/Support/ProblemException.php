<?php

namespace App\Support;

use RuntimeException;

class ProblemException extends RuntimeException
{
    public function __construct(
        public readonly string $problemCode,
        public readonly string $title,
        public readonly int $status,
        public readonly ?string $detail = null,
        public readonly array $extra = [],
        public readonly string $type = 'about:blank',
    ) {
        parent::__construct($title);
    }

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
