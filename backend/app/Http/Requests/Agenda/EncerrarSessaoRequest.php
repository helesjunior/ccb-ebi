<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class EncerrarSessaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['realizada' => ['required', 'boolean'], 'fim_real' => ['nullable', 'date']];
    }
}
