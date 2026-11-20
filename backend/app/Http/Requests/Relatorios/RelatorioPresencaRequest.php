<?php

namespace App\Http\Requests\Relatorios;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Parametros de `GET /reports/attendance`. `nivel` e `id` sao opcionais e, sem
 * eles, o relatorio usa o no do contexto de atuacao.
 */
class RelatorioPresencaRequest extends FormRequest
{
    /** Intervalo maximo aceito, em dias inclusive. */
    public const MAXIMO_DIAS = 366;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nivel' => ['nullable', 'required_with:id', Rule::in(['central', 'regional', 'setor', 'igreja', 'ebi'])],
            'id' => ['nullable', 'required_with:nivel', 'string', 'max:64'],
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'formato' => ['nullable', Rule::in(['json', 'csv'])],
        ];
    }

    /**
     * O intervalo e validado em dias de calendario (ambas as pontas
     * inclusivas), e nao pelo `before_or_equal` do Laravel, que somaria
     * horas quando a entrada trouxesse hora.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['inicio', 'fim'])) {
                    return;
                }

                $dias = CarbonImmutable::parse($this->input('inicio'))
                    ->diffInDays(CarbonImmutable::parse($this->input('fim'))) + 1;

                if ($dias > self::MAXIMO_DIAS) {
                    $validator->errors()->add('fim', 'O periodo pode ter no maximo '.self::MAXIMO_DIAS.' dias.');
                }
            },
        ];
    }
}
