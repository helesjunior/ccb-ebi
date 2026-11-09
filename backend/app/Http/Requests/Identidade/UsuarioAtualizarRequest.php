<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioAtualizarRequest extends FormRequest
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
            'status' => ['sometimes', Rule::in(['ativo', 'suspenso'])],
        ];
    }
}
