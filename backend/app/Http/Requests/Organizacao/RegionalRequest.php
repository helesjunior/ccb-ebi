<?php

namespace App\Http\Requests\Organizacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegionalRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'nome' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'codigo' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('regionais', 'codigo')->ignore($id),
            ],
            'central_nacional_id' => ['nullable', 'string', 'exists:central_nacional,id'],
            'status' => ['nullable', Rule::in(['ativo', 'inativo'])],
        ];
    }
}
