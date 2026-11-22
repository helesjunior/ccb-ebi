<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CriarEscalaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estimativa_participantes' => ['required', 'integer', 'min:0', 'max:65535'],
            'membros' => ['sometimes', 'array'],
            'membros.*.usuario_id' => ['required', 'string', 'max:26'],
            'membros.*.funcao' => ['required', Rule::in(['coordenadora', 'auxiliar'])],
        ];
    }
}
