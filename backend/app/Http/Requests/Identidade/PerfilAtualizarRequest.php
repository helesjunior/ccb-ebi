<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class PerfilAtualizarRequest extends FormRequest
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
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255'],
        ];
    }
}
