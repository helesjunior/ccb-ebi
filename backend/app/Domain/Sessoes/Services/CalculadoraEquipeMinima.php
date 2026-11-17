<?php

namespace App\Domain\Sessoes\Services;

class CalculadoraEquipeMinima
{
    /** @return array{presentes:int, participantes_por_auxiliar:int, auxiliares_necessarios:int, auxiliares_informados:?int, alerta:bool} */
    public function calcular(int $presentes, int $porAuxiliar, ?int $informados): array
    {
        $necessarios = $porAuxiliar > 0 ? (int) ceil($presentes / $porAuxiliar) : 0;

        return [
            'presentes' => $presentes,
            'participantes_por_auxiliar' => $porAuxiliar,
            'auxiliares_necessarios' => $necessarios,
            'auxiliares_informados' => $informados,
            'alerta' => $informados !== null && $presentes > 0 && $informados < $necessarios,
        ];
    }
}
