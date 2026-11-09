<?php

namespace App\Http\Requests\Identidade;

use Illuminate\Foundation\Http\FormRequest;

class AceitarConviteRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ];
    }
}
