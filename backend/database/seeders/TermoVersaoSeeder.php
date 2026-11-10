<?php

namespace Database\Seeders;

use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Models\TermoVersao;
use Illuminate\Database\Seeder;

/**
 * Termo de cadastro para desenvolvimento/teste. O texto e um PLACEHOLDER
 * ("rascunho") aguardando os textos finais do juridico ([ADR-0019], [ADR-0030])
 * — NAO usar em producao com dados reais. Idempotente pela versao: rodar de
 * novo nao duplica nem reabre versoes ja publicadas.
 *
 * [ADR-0019]: ../../docs/06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md
 * [ADR-0030]: ../../docs/06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md
 */
class TermoVersaoSeeder extends Seeder
{
    public const VERSAO = '2026-11-01';

    public function run(): void
    {
        if (TermoVersao::query()->where('finalidade', TermoVersao::FINALIDADE_CADASTRO)->where('versao', self::VERSAO)->exists()) {
            return;
        }

        app(GerirTermos::class)->publicar(
            TermoVersao::FINALIDADE_CADASTRO,
            'consentimento',
            self::VERSAO,
            $this->textoPlaceholder(),
            now(),
        );
    }

    /**
     * Texto provisorio, marcado como rascunho. Substituir pelos textos finais
     * quando o juridico entregar; publicar a nova versao encerra esta.
     */
    private function textoPlaceholder(): string
    {
        return implode("\n", [
            '[RASCUNHO — texto placeholder para desenvolvimento, pendente do jurídico]',
            '',
            'Termo de consentimento para cadastro na plataforma. Ao prosseguir, você',
            'declara que leu e concorda com o tratamento dos seus dados pessoais para',
            'as finalidades de cadastro e uso do sistema, nos termos da LGPD.',
            '',
            'Base legal: consentimento (art. 7º, I, da Lei nº 13.709/2018).',
            'Você pode retirar o consentimento a qualquer momento pela área de',
            'Privacidade do portal.',
        ]);
    }
}
