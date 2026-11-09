<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class VinculoRequest extends FormRequest
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
            'usuario_id' => ['required', 'string', 'max:64'],
            'papel' => ['required', 'string', 'max:64'],
            'tipo_escopo' => ['required', 'string', 'in:central,regional,setor,igreja,ebi'],
            'escopo_id' => ['required', 'string', 'max:64'],
        ];
    }
}
