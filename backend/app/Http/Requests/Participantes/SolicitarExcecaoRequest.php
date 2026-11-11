<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarExcecaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_necessidade' => ['required', 'string', 'max:100'],
            'apoios_necessarios' => ['nullable', 'string', 'max:2000'],
            'justificativa_funcional' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
