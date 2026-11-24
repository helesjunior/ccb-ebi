<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;

class AlterarEscalaRequest extends FormRequest
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
        ];
    }
}
