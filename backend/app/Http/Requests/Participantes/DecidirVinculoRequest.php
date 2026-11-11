<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class DecidirVinculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota sob `can:vinculo.gerenciar`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decisao' => ['required', 'in:aprovar,recusar,pedir_documento,revogar,contestar'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
