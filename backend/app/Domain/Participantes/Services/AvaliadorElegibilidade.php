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

    public function aviso12Anos(Participante $participante, DateTimeInterface $agora, string $fuso, int $dias = 30): ?string
    {
        if ($participante->status !== Participante::STATUS_ATIVO) {
            return null;
        }

        $hoje = DateTimeImmutable::createFromInterface($agora)->setTimezone(new DateTimeZone($fuso))->setTime(0, 0);
        $n = DateTimeImmutable::createFromInterface($participante->data_nascimento);
        $ano = (int) $n->format('Y') + 12;
        [$mes, $dia] = [(int) $n->format('m'), (int) $n->format('d')];

        if ($mes === 2 && $dia === 29 && ! $this->bissexto($ano)) {
            [$mes, $dia] = [3, 1];
        }

        $aniversario = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

        if ($aniversario < $hoje->format('Y-m-d') || $aniversario > $hoje->modify("+{$dias} days")->format('Y-m-d')) {
            return null;
        }

        $excecao = $participante->excecaoVigente();
        $naData = new DateTimeImmutable($aniversario, $hoje->getTimezone());

        if ($excecao !== null && $this->excecaoVigente($excecao, $naData)) {
            return null;
        }

        return $aniversario;
    }

    public function aviso12AnosDaIgreja(string $igrejaId, DateTimeInterface $agora, string $fuso, int $dias = 30): array
    {
        $hoje = DateTimeImmutable::createFromInterface($agora)->setTimezone(new DateTimeZone($fuso))->setTime(0, 0);

        // Pre-filtro no SQL com um dia de folga de cada lado (29/02); o corte exato e o de aviso12Anos.
        // ponytail: excecaoVigente() e uma consulta por candidato (N+1); a janela de 30 dias de uma
        // igreja da poucas linhas. Carregar as excecoes em lote se a lista crescer.
        $candidatos = Participante::query()
            ->where('igreja_referencia_id', $igrejaId)
            ->where('status', Participante::STATUS_ATIVO)
            ->whereBetween('data_nascimento', [
                $hoje->modify('-12 years')->modify('-1 day')->format('Y-m-d'),
                $hoje->modify("+{$dias} days")->modify('-12 years')->modify('+1 day')->format('Y-m-d'),
            ])
            ->get();

        $linhas = [];

        foreach ($candidatos as $participante) {
            $data = $this->aviso12Anos($participante, $agora, $fuso, $dias);
            if ($data !== null) {
                $linhas[] = ['participante' => $participante, 'faz_12_em' => $data];
            }
        }

        usort($linhas, fn (array $a, array $b): int => [$a['faz_12_em'], $a['participante']->nome] <=> [$b['faz_12_em'], $b['participante']->nome]);

        return $linhas;
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
