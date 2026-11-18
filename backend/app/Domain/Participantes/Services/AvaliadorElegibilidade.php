<?php

namespace App\Domain\Participantes\Services;

use App\Domain\Participantes\Contracts\AvaliarElegibilidade;
use App\Domain\Participantes\Contracts\ResultadoElegibilidade;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/**
 * Elegibilidade etaria para a presenca (RN-16, CA-17, CA-19).
 *
 * Regras:
 * - idade < 3: bloqueado SEMPRE, mesmo com excecao inclusiva (CA-19).
 * - 3..11: permitido.
 * - idade >= 12: so com excecao `aprovada` e vigente (CA-17). Excecao suspensa,
 *   revogada, expirada ou com `vigente_ate` no passado bloqueia (RN-15).
 *
 * A data da sessao e resolvida no fuso da igreja (o "hoje" local decide a
 * idade). RN-16: quem nasceu 29/02 completa ano em 01/03 nos anos nao bissextos.
 * Nao depende de presenca/sessao (o chamador passa a data).
 */
class AvaliadorElegibilidade implements AvaliarElegibilidade
{
    public function avaliarPorId(string $participanteId, DateTimeInterface $dataSessao, string $fusoIgreja): ResultadoElegibilidade
    {
        $participante = Participante::query()->find($participanteId)
            ?? throw new RuntimeException("Participante nao encontrado: {$participanteId}");

        return $this->avaliar($participante, $dataSessao, $fusoIgreja, $participante->excecaoVigente());
    }

    public function avaliar(
        Participante $participante,
        DateTimeInterface $dataSessao,
        string $fusoIgreja,
        ?ExcecaoEtaria $excecao = null,
    ): ResultadoElegibilidade {
        $local = DateTimeImmutable::createFromInterface($dataSessao)
            ->setTimezone(new DateTimeZone($fusoIgreja));

        $idade = $this->idadeEm($participante->data_nascimento, $local);

        if ($idade < 3) {
            return ResultadoElegibilidade::bloqueado('idade_minima');
        }

        if ($idade < 12) {
            return ResultadoElegibilidade::permitido();
        }

        if ($excecao === null || ! $this->excecaoVigente($excecao, $local)) {
            return ResultadoElegibilidade::bloqueado('excecao_ausente_ou_invalida');
        }

        return ResultadoElegibilidade::permitido('excecao_aprovada');
    }

    /** Idade em anos completos na data local, tratando 29/02 (RN-16). */
    private function idadeEm(DateTimeInterface $nascimento, DateTimeImmutable $local): int
    {
        $n = DateTimeImmutable::createFromInterface($nascimento);

        $idade = (int) $local->format('Y') - (int) $n->format('Y');

        // "Aniversario local" deste ano: 29/02 em ano nao bissexto cai em 01/03.
        $mesNasc = (int) $n->format('m');
        $diaNasc = (int) $n->format('d');

        if ($mesNasc === 2 && $diaNasc === 29 && ! $this->bissexto((int) $local->format('Y'))) {
            $mesNasc = 3;
            $diaNasc = 1;
        }

        $aniversarioEsteAno = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-%02d', (int) $local->format('Y'), $mesNasc, $diaNasc),
            $local->getTimezone(),
        );

        // Comparacao por calendario (mesmo fuso): a data local e anterior ao
        // aniversario local deste ano?
        if ($aniversarioEsteAno instanceof DateTimeImmutable
            && $local->format('Y-m-d') < $aniversarioEsteAno->format('Y-m-d')) {
            $idade--;
        }

        return $idade;
    }

    private function excecaoVigente(ExcecaoEtaria $excecao, DateTimeImmutable $local): bool
    {
        if ($excecao->status !== ExcecaoEtaria::STATUS_APROVADA) {
            return false;
        }

        if ($excecao->vigente_ate !== null && $excecao->vigente_ate->format('Y-m-d') < $local->format('Y-m-d')) {
            return false;
        }

        return true;
    }

    private function bissexto(int $ano): bool
    {
        return ($ano % 4 === 0 && $ano % 100 !== 0) || $ano % 400 === 0;
    }
}
