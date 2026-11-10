<?php

namespace App\Http\Requests\Privacidade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitacaoRequest extends FormRequest
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
            'tipo' => ['required', 'string', Rule::in(['acesso', 'correcao', 'revogacao'])],
            'descricao' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
