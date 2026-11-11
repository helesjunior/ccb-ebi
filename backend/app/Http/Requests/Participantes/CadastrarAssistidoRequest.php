<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class CadastrarAssistidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // rota sob `can:participante.cadastrar`
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
            'responsavel_nome' => ['required', 'string', 'max:255'],
            'responsavel_telefone' => ['nullable', 'string', 'max:30'],
            'tipo_relacao' => ['required', 'string', 'max:50'],
            // Evidencia obrigatoria (CA-16): arquivo real, no pipeline de storage.
            'evidencia' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
