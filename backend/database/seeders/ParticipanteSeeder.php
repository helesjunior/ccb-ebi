<?php

namespace Database\Seeders;

use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Participantes\Models\ExcecaoEtaria;
use App\Domain\Participantes\Models\Participante;
use App\Domain\Participantes\Models\ResponsavelParticipante;
use App\Domain\Participantes\Services\AvaliadorElegibilidade;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * Casos-limite de participantes para desenvolvimento e demonstração (E03).
 *
 * Cobre a régua da idade (RN-16, CA-17/CA-19) e os vínculos (CA-04), além do
 * estado de mesclagem. Idempotente por matrícula: pode rodar repetidas vezes.
 * Usa dados fictícios.
 *
 * Os cadastros recebem a **igreja de referência** do seed organizacional (quando
 * existe) para que o escopo territorial (H3) e a mesclagem (M6) sejam
 * demonstráveis na interface com a coordenadora de demonstração.
 */
class ParticipanteSeeder extends Seeder
{
    public function run(): void
    {
        $avaliador = app(AvaliadorElegibilidade::class);
        $hoje = new DateTimeImmutable('now');

        // Igreja do seed organizacional (OrganizacaoSeeder). Sem ela, os
        // cadastros ficam sem igreja de referência (nenhum escopo territorial).
        $igrejaId = Igreja::query()->where('codigo', '240003')->value('id');

        // 2 anos: abaixo do mínimo — nunca elegível, mesmo com exceção (CA-19).
        $bebe = $this->participante('Pinguinho (2 anos)', $hoje->modify('-2 years'), igrejaId: $igrejaId);
        $avaliador->avaliar($bebe, $hoje, 'America/Sao_Paulo'); // demonstra o bloqueio

        // 7 anos: faixa plena, elegível sem exceção.
        $crianca = $this->participante('Patota (7 anos)', $hoje->modify('-7 years'), igrejaId: $igrejaId);
        $this->vinculo($crianca, 'Mãe da Patota', ResponsavelParticipante::VALIDACAO_APROVADA);

        // 13 anos: só elegível com exceção aprovada e vigente (CA-17).
        $adolescente = $this->participante('Patota Grande (13 anos)', $hoje->modify('-13 years'), igrejaId: $igrejaId);
        $this->vinculo($adolescente, 'Pai do Patota Grande', ResponsavelParticipante::VALIDACAO_APROVADA);
        ExcecaoEtaria::query()->create([
            'participante_id' => $adolescente->id,
            'tipo_necessidade' => 'neurodivergencia',
            'apoios_necessarios' => 'Rotina previsível e pausas.',
            'justificativa_funcional' => 'Acompanhamento contínuo para participação plena.',
            'status' => ExcecaoEtaria::STATUS_APROVADA,
            'decidido_em' => now(),
            'vigente_ate' => $hoje->modify('+1 year')->format('Y-m-d'),
        ]);

        // Vínculo pendente: o dependente NÃO aparece para o responsável (CA-04).
        $pendente = $this->participante('Patota Pendente (9 anos)', $hoje->modify('-9 years'), igrejaId: $igrejaId);
        $this->vinculo($pendente, 'Responsável Pendente', ResponsavelParticipante::VALIDACAO_PENDENTE);

        // Cadastro assistido: responsável sem conta, vínculo nasce aprovado.
        $assistido = $this->participante(
            'Patota Assistida (5 anos)',
            $hoje->modify('-5 years'),
            origem: Participante::ORIGEM_ASSISTIDO,
            igrejaId: $igrejaId,
        );
        $this->vinculo($assistido, 'Responsável sem e-mail', ResponsavelParticipante::VALIDACAO_APROVADA, usuarioId: null);

        // Duplicado mesclado: o cadastro encerrado fica fora da busca (CA-27).
        $mesclado = $this->participante('Patota Duplicada (8 anos)', $hoje->modify('-8 years'), igrejaId: $igrejaId);
        $mesclado->forceFill(['status' => 'mesclado'])->save();
    }

    private function participante(
        string $nome,
        DateTimeImmutable $nascimento,
        string $origem = Participante::ORIGEM_RESPONSAVEL,
        ?string $igrejaId = null,
    ): Participante {
        $matricula = 'SEED'.substr(hash('sha256', $nome), 0, 4);

        $participante = Participante::query()->firstOrCreate(
            ['matricula' => $matricula],
            [
                'nome' => $nome,
                'data_nascimento' => $nascimento->format('Y-m-d'),
                'status' => Participante::STATUS_ATIVO,
                'origem_cadastro' => $origem,
                'igreja_referencia_id' => $igrejaId,
            ],
        );

        // Repõe a igreja de referência em execuções repetidas (idempotência).
        if ($igrejaId !== null && $participante->igreja_referencia_id !== $igrejaId) {
            $participante->forceFill(['igreja_referencia_id' => $igrejaId])->save();
        }

        return $participante;
    }

    private function vinculo(
        Participante $participante,
        string $nome,
        string $validacao,
        ?string $usuarioId = null,
    ): ResponsavelParticipante {
        return ResponsavelParticipante::query()->firstOrCreate(
            ['participante_id' => $participante->id, 'nome' => $nome],
            [
                'usuario_id' => $usuarioId,
                'tipo_relacao' => 'responsavel',
                'permissoes' => [],
                'status' => 'ativo',
                'validacao' => $validacao,
                'conferido_em' => $validacao === ResponsavelParticipante::VALIDACAO_APROVADA ? now() : null,
                'inicio' => now()->toDateString(),
            ],
        );
    }
}
