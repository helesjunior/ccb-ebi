<?php

namespace App\Http\Requests\Organizacao;

use Illuminate\Foundation\Http\FormRequest;

class TransferenciaRequest extends FormRequest
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
            'sector_id' => ['required', 'string', 'exists:setores,id'],
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
