<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class ContingenciaPresencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'participante_id' => ['required', 'ulid', 'exists:participante,id'],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
