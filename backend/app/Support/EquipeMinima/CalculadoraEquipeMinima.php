<?php

namespace App\Support\EquipeMinima;

/**
 * Formula unica da equipe minima (RF-07/RF-08), usada pelo aviso da sessao e
 * pela escala: quantos auxiliares uma quantidade de participantes exige para um
 * divisor de atendimento por auxiliar.
 */
class CalculadoraEquipeMinima
{
    /** @return array{presentes:int, participantes_por_auxiliar:int, auxiliares_necessarios:int, auxiliares_informados:?int, alerta:bool} */
    public function calcular(int $presentes, int $porAuxiliar, ?int $informados): array
    {
        $necessarios = $this->auxiliaresNecessarios($presentes, $porAuxiliar);

        return [
            'presentes' => $presentes,
            'participantes_por_auxiliar' => $porAuxiliar,
            'auxiliares_necessarios' => $necessarios,
            'auxiliares_informados' => $informados,
            'alerta' => $informados !== null && $presentes > 0 && $informados < $necessarios,
        ];
    }

    /**
     * Minima necessaria para uma escala: ao menos uma coordenadora e tantos
     * auxiliares quantos a estimativa de participantes exigir. O divisor e o
     * gravado na escala, nao o da agenda no momento da leitura.
     *
     * @return array{estimativa:int, participantes_por_auxiliar:int, coordenadoras_necessarias:int, auxiliares_necessarios:int, coordenadoras_escaladas:int, auxiliares_escalados:int, atingida:bool}
     */
    public function paraEscala(int $estimativa, int $porAuxiliar, int $coordenadoras, int $auxiliares): array
    {
        $necessarios = $this->auxiliaresNecessarios($estimativa, $porAuxiliar);

        return [
            'estimativa' => $estimativa,
            'participantes_por_auxiliar' => $porAuxiliar,
            'coordenadoras_necessarias' => 1,
            'auxiliares_necessarios' => $necessarios,
            'coordenadoras_escaladas' => $coordenadoras,
            'auxiliares_escalados' => $auxiliares,
            'atingida' => $coordenadoras >= 1 && $auxiliares >= $necessarios,
        ];
    }

    private function auxiliaresNecessarios(int $quantidade, int $porAuxiliar): int
    {
        return $porAuxiliar > 0 ? (int) ceil($quantidade / $porAuxiliar) : 0;
    }
}
