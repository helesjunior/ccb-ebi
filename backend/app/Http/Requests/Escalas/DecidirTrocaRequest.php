<?php

namespace App\Http\Requests\Escalas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecidirTrocaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decisao' => ['required', Rule::in(['aprovar', 'recusar'])],
            'substituto_usuario_id' => ['nullable', 'string', 'max:26'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function decisao(): string
    {
        return (string) $this->validated('decisao');
    }

    public function substituto(): ?string
    {
        $valor = $this->validated('substituto_usuario_id');

        return $valor === null ? null : (string) $valor;
    }

    public function motivo(): ?string
    {
        $valor = $this->validated('motivo');

        return $valor === null ? null : (string) $valor;
    }
}
