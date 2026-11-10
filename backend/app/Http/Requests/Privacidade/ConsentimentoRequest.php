<?php

namespace App\Http\Requests\Privacidade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsentimentoRequest extends FormRequest
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
            'termo_versao_id' => ['required', 'string', 'exists:termo_versao,id'],
            'decisao' => ['required', 'string', Rule::in(['aceito', 'recusado'])],
            'retirar' => ['sometimes', 'boolean'],
            // Cadastro assistido (E03) ainda nao e aceito nesta etapa.
            'participante_id' => ['prohibited'],
        ];
    }
}
