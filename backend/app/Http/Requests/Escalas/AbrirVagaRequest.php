<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;

class AbrirVagaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'substitui_membro_id' => ['nullable', 'string', 'max:26'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }
}
