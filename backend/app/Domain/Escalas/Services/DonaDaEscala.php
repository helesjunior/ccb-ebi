<?php

namespace App\Domain\Escalas\Services;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Identidade\Contracts\ConsultarEquipeEbi;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Support\ProblemException;

/**
 * Regra de dona da escala (E07, evolucao): a dona e a coordenadora da equipe
 * de origem. A dona e a chefe/adm_igreja escrevem; so a dona escolhe oferta
 * (a chefe so e avisada).
 */
class DonaDaEscala
{
    private const PAPEIS_SUPERVISAO = ['coordenadora_chefe', 'adm_igreja'];

    /** Papeis com que a dona pode atuar (a chefe dona inclui `coordenadora_chefe`). */
    private const PAPEIS_DONA = ['coordenadora', 'coordenadora_chefe'];

    public function __construct(private readonly ConsultarEquipeEbi $equipe) {}

    public function donaId(string $escalaId): string
    {
        return (string) Equipe::query()
            ->whereIn('id', Escala::query()->whereKey($escalaId)->select('equipe_id'))
            ->value('coordenadora_id');
    }

    /**
     * Escopos em que a pessoa tem papel de equipe vigente hoje, para o cartao
     * de vagas abertas (`/me`).
     *
     * @return list<array{ebi_id: ?string, igreja_id: string}>
     */
    public function escoposDeEquipe(string $usuarioId): array
    {
        return $this->equipe->escoposDeEquipeVigentes($usuarioId);
    }

    /**
     * Supervisao so vale dentro do territorio (`$alcanca`): a chefe de outro EBI
     * nao supervisiona esta escala nem ve os nomes das ofertantes. A dona decide
     * pelo proprio vinculo de coordenadora, vigente hoje.
     */
    public function podeGerir(string $escalaId, ContextoDecisao $ctx, bool $alcanca = true): bool
    {
        if (in_array($ctx->papelCodigo, self::PAPEIS_SUPERVISAO, true)) {
            return $alcanca;
        }

        return $this->podeEscolherOferta($escalaId, $ctx);
    }

    public function garantirGerir(string $escalaId, ContextoDecisao $ctx, bool $alcanca = true): void
    {
        if (! $this->podeGerir($escalaId, $ctx, $alcanca)) {
            throw new ProblemException(
                'nao_e_dona_da_escala',
                'Acesso negado',
                403,
                'Só a dona da escala (ou a supervisão) pode alterá-la.',
                ['dona_id' => $this->donaId($escalaId)],
            );
        }
    }

    /**
     * Escolher oferta e da dona atuando como coordenadora, com vinculo vigente
     * hoje; a chefia so e avisada e nao escolhe. Vale tambem para a chefe que e
     * dona da equipe, mas ela e reconhecida pelo vinculo de coordenadora.
     */
    public function podeEscolherOferta(string $escalaId, ContextoDecisao $ctx): bool
    {
        if (! in_array($ctx->papelCodigo, self::PAPEIS_DONA, true) || $ctx->usuarioId === '') {
            return false;
        }

        $escala = Escala::query()->find($escalaId);
        if ($escala === null) {
            return false;
        }

        $donaId = $this->donaId($escalaId);
        if ($donaId === '' || $donaId !== $ctx->usuarioId) {
            return false;
        }

        $papeis = $this->equipe->papeisVigentesHoje([$donaId], (string) $escala->ebi_id, (string) $escala->igreja_id);

        return in_array('coordenadora', $papeis[$donaId] ?? [], true)
            || in_array('coordenadora_chefe', $papeis[$donaId] ?? [], true);
    }

    public function garantirEscolher(string $escalaId, ContextoDecisao $ctx): void
    {
        if (! $this->podeEscolherOferta($escalaId, $ctx)) {
            throw new ProblemException(
                'nao_e_dona_da_escala',
                'Acesso negado',
                403,
                'Só a dona da escala escolhe quem preenche a vaga.',
                ['dona_id' => $this->donaId($escalaId)],
            );
        }
    }

    /** Membros ativos da escala (quem ja ocupa lugar). */
    /** @return array<int, string> */
    public function usuariosNaEscala(string $escalaId): array
    {
        return EscalaMembro::query()
            ->where('escala_id', $escalaId)
            ->where('status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
            ->pluck('usuario_id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }
}
