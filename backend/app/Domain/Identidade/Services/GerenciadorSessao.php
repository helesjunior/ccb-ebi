<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Domain\Identidade\Models\SessaoLogin;
use App\Domain\Identidade\Models\Usuario;
use App\Support\ProblemException;
use Illuminate\Contracts\Auth\Authenticatable;

class GerenciadorSessao implements GerenciarSessao
{
    /** Responsavel: sessoes mais longas (12 h). */
    private const HORAS_RESPONSAVEL = 12;

    /** Demais perfis: 8 h. */
    private const HORAS_PADRAO = 8;

    public function abrir(Usuario $usuario, string $dispositivo): SessaoLogin
    {
        $agora = now();

        return SessaoLogin::query()->create([
            'usuario_id' => $usuario->id,
            'dispositivo' => $dispositivo,
            'criada_em' => $agora,
            'expira_em' => $agora->copy()->addHours($this->horasDeSessao($usuario)),
        ]);
    }

    public function listar(Authenticatable $usuario): array
    {
        $usuario = $this->comoUsuario($usuario);

        return SessaoLogin::query()
            ->where('usuario_id', $usuario->id)
            ->whereNull('revogada_em')
            ->where('expira_em', '>', now())
            ->orderByDesc('criada_em')
            ->get()
            ->map(fn (SessaoLogin $sessao): array => [
                'id' => (string) $sessao->id,
                'dispositivo' => (string) $sessao->dispositivo,
                'criada_em' => $sessao->criada_em->toIso8601String(),
                'expira_em' => $sessao->expira_em->toIso8601String(),
            ])
            ->all();
    }

    public function revogar(Authenticatable $usuario, string $id): void
    {
        $usuario = $this->comoUsuario($usuario);

        SessaoLogin::query()
            ->where('usuario_id', $usuario->id)
            ->whereKey($id)
            ->whereNull('revogada_em')
            ->update(['revogada_em' => now()]);
    }

    private function horasDeSessao(Usuario $usuario): int
    {
        $codigos = $usuario->vinculosVigentes()
            ->with('papel')
            ->get()
            ->map(fn ($vinculo) => $vinculo->papel?->codigo)
            ->filter()
            ->unique();

        $apenasResponsavel = $codigos->isNotEmpty() && $codigos->every(fn (?string $codigo) => $codigo === 'responsavel');

        return $apenasResponsavel ? self::HORAS_RESPONSAVEL : self::HORAS_PADRAO;
    }

    private function comoUsuario(Authenticatable $usuario): Usuario
    {
        if (! $usuario instanceof Usuario) {
            throw new ProblemException('usuario_invalido', 'Acesso negado', 403, 'Usuário de identidade inválido.');
        }

        return $usuario;
    }
}
