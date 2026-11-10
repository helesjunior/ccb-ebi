<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
            // CA-16: aceite obrigatorio do termo de cadastro vigente no cadastro.
            'termo_versao_id' => ['required', 'exists:termo_versao,id'],
            'aceite' => ['required', 'accepted'],
        ];
    }
}
