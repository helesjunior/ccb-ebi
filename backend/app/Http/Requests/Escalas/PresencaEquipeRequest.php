<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;

class PresencaEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'membros' => ['required', 'array', 'min:1'],
            'membros.*.id' => ['required', 'string', 'max:26'],
            'membros.*.presente' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, array{id: string, presente: bool}> */
    public function membros(): array
    {
        /** @var array<int, array{id: string, presente: bool}> $membros */
        $membros = array_values($this->validated('membros'));

        return $membros;
    }
}
