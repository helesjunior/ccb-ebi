<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class EstornarPresencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:3', 'max:255']]; // evento_auditoria.motivo e varchar(255).
    }
}
