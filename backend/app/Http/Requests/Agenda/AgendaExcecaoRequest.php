<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class AgendaExcecaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', 'string', 'in:feriado,cancelamento,horario_especial'],
            'data' => ['required', 'date_format:Y-m-d'],
            'agenda_ebi_id' => ['nullable', 'ulid'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'motivo' => ['required', 'string', 'min:3', 'max:247'], // evento_auditoria.motivo e varchar(255), com o prefixo "agenda: ".
        ];
    }
}
