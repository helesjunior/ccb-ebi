<?php

namespace App\Http\Requests\Organizacao;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IgrejaRequest extends FormRequest
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
        $igrejaId = $this->route('id');

        return [
            'codigo' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:20',
                Rule::unique('igrejas', 'codigo')->ignore($igrejaId),
            ],
            'nome' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'status' => ['nullable', Rule::in(['ativo', 'inativo'])],
        ];
    }
}
