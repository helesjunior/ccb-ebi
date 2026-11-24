<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncluirMembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'string', 'max:26'],
            'funcao' => ['required', Rule::in(['coordenadora', 'auxiliar'])],
        ];
    }
}
