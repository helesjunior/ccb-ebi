<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\GerirConvite;
use App\Domain\Identidade\Models\Convite;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Notifications\ConviteEbi;
use App\Support\ProblemException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Convites da equipe (RF-05). Regra de autoridade: o convidante so convida para
 * (papel, escopo) que ele mesmo poderia ocupar — o escopo-alvo tem de estar
 * coberto pela sua subarvore e o papel nao pode exceder o seu na hierarquia.
 */
class GerenciadorConvite implements GerirConvite
{
    private const DIAS_VALIDADE = 7;

    public function __construct(
        private readonly AutoridadeDePapel $autoridade,
        private readonly GuardaChefe $guarda,
    ) {}

    public function criar(
        ContextoAtuacao $contexto,
        string $email,
        string $papelCodigo,
        string $tipoEscopo,
        string $escopoId,
        string $convidanteId,
    ): Convite {
        $papel = Papel::query()->where('codigo', $papelCodigo)->first();

        if ($papel === null) {
            throw new ProblemException('papel_invalido', 'Convite inválido', 422, 'Papel inexistente.');
        }

        $this->garantirAutoridade($contexto, $papelCodigo, $tipoEscopo, $escopoId);

        // Convite que perde a vaga da chefe continua pendente: a checagem aqui
        // so evita prometer o que ja esta ocupado.
        $this->guarda->garantirEscopo($papelCodigo, $tipoEscopo);
        $this->guarda->garantirVaga($papelCodigo, $escopoId);

        $token = Str::random(64);

        $convite = Convite::query()->create([
            'email_normalizado' => Usuario::emailNormalizado($email),
            'papel_id' => $papel->id,
            'tipo_escopo' => $tipoEscopo,
            'escopo_id' => $escopoId,
            'token_hash' => hash('sha256', $token),
            'expira_em' => now()->addDays(self::DIAS_VALIDADE),
            'convidante_id' => $convidanteId,
        ]);

        Notification::route('mail', (string) $convite->email_normalizado)
            ->notify(new ConviteEbi($convite, $token));

        return $convite;
    }

    public function listar(ContextoAtuacao $contexto): array
    {
        return Convite::query()
            ->with('papel')
            ->latest('id')
            ->get()
            ->filter(fn (Convite $c): bool => $this->cobre($contexto, $c->tipo_escopo, (string) $c->escopo_id))
            ->map(fn (Convite $c): array => [
                'id' => (string) $c->id,
                'email' => (string) $c->email_normalizado,
                'papel' => $c->papel?->codigo,
                'tipo_escopo' => (string) $c->tipo_escopo,
                'escopo_id' => (string) $c->escopo_id,
                'expira_em' => $c->expira_em->toIso8601String(),
                'pendente' => $c->pendente(),
            ])
            ->values()
            ->all();
    }

    public function cancelar(ContextoAtuacao $contexto, string $id): void
    {
        $convite = Convite::query()->find($id);

        if ($convite === null) {
            return;
        }

        if (! $this->cobre($contexto, $convite->tipo_escopo, (string) $convite->escopo_id)) {
            throw new ProblemException('convite_fora_escopo', 'Acesso negado', 403, 'Convite fora do seu escopo.');
        }

        if ($convite->cancelado_em === null && $convite->aceito_em === null) {
            $convite->forceFill(['cancelado_em' => now()])->save();
        }
    }

    private function garantirAutoridade(ContextoAtuacao $contexto, string $papelCodigo, string $tipoEscopo, string $escopoId): void
    {
        if (! $this->autoridade->podeSobrePapel($contexto, $papelCodigo)) {
            throw new ProblemException('convite_sem_autoridade', 'Convite negado', 403, 'O papel excede a sua autoridade.');
        }

        if (! $this->autoridade->cobre($contexto, $tipoEscopo, $escopoId)) {
            throw new ProblemException('convite_fora_escopo', 'Convite negado', 403, 'O escopo está fora da sua subárvore.');
        }
    }

    private function cobre(ContextoAtuacao $contexto, string $tipoEscopo, string $escopoId): bool
    {
        return $this->autoridade->cobre($contexto, $tipoEscopo, $escopoId);
    }
}
