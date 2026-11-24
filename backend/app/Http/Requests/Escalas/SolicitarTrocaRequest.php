<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarTrocaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'escala_membro_id' => ['required', 'string', 'max:26'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
