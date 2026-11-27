<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class CorrigirSessaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tema' => ['sometimes', 'nullable', 'string', 'max:255'],
            'passagem_biblica' => ['sometimes', 'nullable', 'string', 'max:255'],
            'inicio_real' => ['sometimes', 'nullable', 'date'],
            'fim_real' => ['sometimes', 'nullable', 'date'],
            'realizada' => ['sometimes', 'nullable', 'boolean'],
            'auxiliares_escalados' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'motivo' => ['nullable', 'string', 'min:3', 'max:255'], // evento_auditoria.motivo e varchar(255).
        ];
    }
}
