<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class CadastrarParticipanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // o Gate `can:participante.cadastrar` cobre a rota
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'nome_social' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['required', 'date', 'before_or_equal:today'],
            'igreja_referencia_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
