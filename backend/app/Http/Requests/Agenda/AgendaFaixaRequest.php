<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgendaFaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dia_semana' => [Rule::requiredIf($this->isMethod('post')), 'integer', 'between:1,7'],
            'hora_inicio' => [Rule::requiredIf($this->isMethod('post')), 'date_format:H:i'],
            'hora_fim' => [Rule::requiredIf($this->isMethod('post')), 'date_format:H:i', 'after:hora_inicio'],
            'inicio_vigencia' => [Rule::requiredIf($this->isMethod('post')), 'date', 'date_format:Y-m-d'],
            'fim_vigencia' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:inicio_vigencia'],
        ];
    }
}
