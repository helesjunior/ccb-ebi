<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class SolicitarVinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota autenticada; a propriedade e verificada no servico
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo_relacao' => ['required', 'string', 'max:50'],
            'telefone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
