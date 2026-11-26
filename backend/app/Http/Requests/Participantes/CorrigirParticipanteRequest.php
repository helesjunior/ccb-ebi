<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class CorrigirParticipanteRequest extends FormRequest
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
            'nome' => ['sometimes', 'string', 'max:255'],
            'nome_social' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data_nascimento' => ['sometimes', 'date', 'before_or_equal:today'],
            'igreja_referencia_id' => ['sometimes', 'nullable', 'string', 'exists:igrejas,id'],
            // evento_auditoria.motivo e varchar(255).
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
