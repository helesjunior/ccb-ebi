<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPresencaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participante_id' => ['required', 'ulid', 'exists:participante,id'],
            'origem' => ['nullable', 'in:web,app'],
        ];
    }
}
