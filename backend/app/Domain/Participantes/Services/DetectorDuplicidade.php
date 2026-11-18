<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Participantes\Models\Participante;
use Illuminate\Support\Str;

/**
 * Detector de duplicidade (CA-27): compara nome normalizado (minusculas, sem
 * acento, espaco unico) + data de nascimento. Devolve apenas o sinal (os
 * candidatos), nunca dados de terceiros a quem nao pode ler — o chamador filtra
 * pelo escopo.
 */
class DetectorDuplicidade
{
    /**
     * @param  array<string, mixed>  $dados
     * @return array<int, Participante>
     */
    public function detectar(array $dados): array
    {
        $nome = $this->normalizar((string) ($dados['nome'] ?? ''));
        if ($nome === '') {
            return [];
        }

        $consulta = Participante::query()->where('status', '!=', 'mesclado');

        if (! empty($dados['data_nascimento'])) {
            $consulta->whereDate('data_nascimento', $dados['data_nascimento']);
        }

        if (! empty($dados['igreja_referencia_id'])) {
            $consulta->where('igreja_referencia_id', $dados['igreja_referencia_id']);
        }

        return $consulta->get()
            ->filter(fn (Participante $p): bool => $this->casa($this->normalizar($p->nome), $nome))
            ->values()
            ->all();
    }

    /**
     * `q` e termo de busca do operador: casa o nome completo exato (dedup do
     * cadastro) e tambem prefixo de qualquer palavra do nome, para que buscar
     * "Patota" ache "Patota (7 anos)".
     */
    private function casa(string $nomeNormalizado, string $termo): bool
    {
        if ($nomeNormalizado === $termo) {
            return true;
        }

        foreach (preg_split('/\s+/', $nomeNormalizado) ?: [] as $palavra) {
            if ($palavra !== '' && str_starts_with($palavra, $termo)) {
                return true;
            }
        }

        return false;
    }

    private function normalizar(string $texto): string
    {
        $semAcento = Str::ascii($texto);

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($semAcento)) ?? '');
    }
}
