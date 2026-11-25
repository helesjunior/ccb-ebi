<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;

class CriarEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'coordenadora_id' => ['required', 'string', 'max:26'],
        ];
    }
}
