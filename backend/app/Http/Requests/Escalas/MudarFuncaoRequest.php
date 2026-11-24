<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MudarFuncaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'funcao' => ['required', Rule::in(['coordenadora', 'auxiliar'])],
        ];
    }
}
