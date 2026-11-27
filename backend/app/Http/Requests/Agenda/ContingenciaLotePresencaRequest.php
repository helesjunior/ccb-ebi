<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class ContingenciaLotePresencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participante_ids' => ['required', 'array', 'min:1', 'max:100'],
            // A existencia e conferida por linha, para um id ruim nao derrubar o lote.
            'participante_ids.*' => ['required', 'ulid', 'distinct'],
            'motivo' => ['required', 'string', 'min:3', 'max:255'], // evento_auditoria.motivo e varchar(255).
        ];
    }
}
