<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class DecidirExcecaoRequest extends FormRequest
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
            'decisao' => ['required', 'in:aprovar,recusar,suspender,revogar'],
            'vigente_ate' => ['nullable', 'date'],
            'revisao_prevista_em' => ['nullable', 'date'],
            'responsavel_concordante_id' => ['nullable', 'string', 'max:255'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
