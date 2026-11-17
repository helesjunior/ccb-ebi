<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class GerarSessoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'de' => ['nullable', 'date', 'date_format:Y-m-d'],
            'ate' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:de'],
            'semanas' => ['nullable', 'integer', 'between:1,12'],
        ];
    }
}
