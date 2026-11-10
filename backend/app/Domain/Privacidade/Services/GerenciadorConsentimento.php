<?php

namespace App\Domain\Privacidade\Services;

use App\Domain\Privacidade\Contracts\ConsultarConsentimentos;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Domain\Privacidade\Models\ManifestacaoPrivacidade;
use App\Domain\Privacidade\Models\TermoVersao;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;

class GerenciadorConsentimento implements ConsultarConsentimentos, RegistrarConsentimento
{
    public function __construct(private readonly GerirTermos $termos) {}

    public function registrar(
        string $usuarioId,
        string $termoVersaoId,
        string $decisao,
        string $origem,
        EvidenciaAceite $evidencia,
    ): ManifestacaoPrivacidade {
        $termo = TermoVersao::query()->find($termoVersaoId)
            ?? throw new ProblemException(
                'termo_inexistente',
                'Termo inválido',
                422,
                'Versão de termo inexistente.',
            );

        // CA-16: so a versao vigente da finalidade pode ser manifestada — mesma
        // regra do `register-guardian` (`GerenciadorConta`). Versoes futuras ou
        // encerradas sao recusadas.
        $vigente = $this->termos->vigente($termo->finalidade);

        if ($vigente === null || $vigente->id !== $termo->id) {
            throw new ProblemException(
                'termo_indisponivel',
                'Termo indisponível',
                422,
                'A versão de termo informada não é a vigente para a finalidade.',
            );
        }

        return ManifestacaoPrivacidade::query()->create([
            'usuario_id' => $usuarioId,
            'termo_versao_id' => $termo->id,
            'decisao' => $decisao,
            'origem' => $origem,
            'evidencia' => $this->textoEvidencia($decisao, $evidencia, $termo),
            'ocorrido_em' => now(),
        ]);
    }

    public function retirar(
        string $usuarioId,
        string $termoVersaoId,
        EvidenciaAceite $evidencia,
    ): ManifestacaoPrivacidade {
        return $this->registrar(
            $usuarioId,
            $termoVersaoId,
            ManifestacaoPrivacidade::DECISAO_RETIRADO,
            ManifestacaoPrivacidade::ORIGEM_PROPRIO,
            $evidencia,
        );
    }

    /** @return array<int, ManifestacaoPrivacidade> */
    public function atuais(string $usuarioId): array
    {
        $manifestacoes = ManifestacaoPrivacidade::query()
            ->with('termoVersao')
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->get();

        // A manifestacao mais recente de cada finalidade e a que vale hoje.
        return $manifestacoes
            ->unique(fn (ManifestacaoPrivacidade $m): ?string => $m->termoVersao?->finalidade)
            ->values()
            ->all();
    }

    public function encontrar(string $manifestacaoId): ?ManifestacaoPrivacidade
    {
        return ManifestacaoPrivacidade::query()
            ->with('termoVersao')
            ->find($manifestacaoId);
    }

    private function textoEvidencia(
        string $decisao,
        EvidenciaAceite $evidencia,
        TermoVersao $termo,
    ): string {
        $acao = match ($decisao) {
            ManifestacaoPrivacidade::DECISAO_ACEITO => 'aceite',
            ManifestacaoPrivacidade::DECISAO_RECUSADO => 'recusa',
            ManifestacaoPrivacidade::DECISAO_RETIRADO => 'retirada',
            default => $decisao,
        };

        return sprintf(
            '%s digital ip=%s ua=%s termo=%s',
            $acao,
            $evidencia->ip,
            $evidencia->userAgent,
            $evidencia->termoHash ?? $termo->texto_hash,
        );
    }
}
