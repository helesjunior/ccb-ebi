<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarVinculosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'validacao' => ['sometimes', Rule::in([
                'pendente', 'aprovada', 'aguardando_documento', 'recusada', 'todos',
            ])],
            'finalidade' => ['required', 'string', 'max:120'],
        ];
    }
}
