<?php

namespace App\Http\Requests\Participantes;

use Illuminate\Foundation\Http\FormRequest;

class MesclarParticipanteRequest extends FormRequest
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
            'duplicado_id' => ['required', 'string', 'max:255'],
        ];
    }
}
