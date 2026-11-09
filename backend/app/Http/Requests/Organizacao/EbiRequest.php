<?php

namespace App\Http\Requests\Organizacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EbiRequest extends FormRequest
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
            'nome' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'igreja_id' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'exists:igrejas,id'],
            'participantes_por_auxiliar' => ['nullable', 'integer', 'min:1', 'max:255'],
            'status' => ['nullable', Rule::in(['ativo', 'inativo'])],
        ];
    }
}
