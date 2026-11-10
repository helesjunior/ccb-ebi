<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Identidade\Contracts\GerirUsuario;
use App\Domain\Identidade\Models\SessaoLogin;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Support\ProblemException;

/**
 * Atualiza dados da equipe (nome, status). Toda mudanca de status e auditada
 * (RN-10); suspender invalida as sessoes do usuario (forca re-login).
 *
 * Autorizacao por recurso: o ator so gere um alvo cujos vinculos vigentes
 * estejam integralmente dentro da sua autoridade (papel) e subarvore (escopo).
 * Sem isso, um adm_igreja suspenderia um super_admin (escalada vertical).
 */
class GerenciadorUsuario implements GerirUsuario
{
    public function __construct(
        private readonly RegistrarEvento $auditoria,
        private readonly AutoridadeDePapel $autoridade,
    ) {}

    public function atualizar(ContextoAtuacao $contexto, string $id, array $dados): ?object
    {
        $usuario = Usuario::query()->find($id);

        if ($usuario === null) {
            return null;
        }

        $this->garantirAutoridadeSobreUsuario($contexto, $usuario);

        $statusAnterior = $usuario->status;

        if (array_key_exists('nome', $dados)) {
            $usuario->nome = (string) $dados['nome'];
        }

        if (array_key_exists('status', $dados)) {
            $usuario->status = (string) $dados['status'];
        }

        $usuario->save();

        if (array_key_exists('status', $dados) && $statusAnterior !== $usuario->status) {
            if ($usuario->status === 'suspenso') {
                // Suspensao revoga as sessoes (tokens Sanctum) e as marca como
                // encerradas, para nao aparecerem como vigentes — RN-10 / RF-05.
                $usuario->tokens()->delete();
                SessaoLogin::query()
                    ->where('usuario_id', $usuario->id)
                    ->whereNull('revogada_em')
                    ->update(['revogada_em' => now()]);
            }

            $this->auditoria->registrar([
                'acao' => 'alterar_status_usuario',
                'recurso' => 'usuario',
                'ator_id' => $contexto->usuarioId,
                'origem' => 'api',
                'escopo' => $contexto->tipoEscopo.':'.$contexto->escopoId,
                'motivo' => null,
                'metadados_minimizados' => [
                    'usuario_id' => (string) $usuario->id,
                    'de' => $statusAnterior,
                    'para' => $usuario->status,
                ],
                'ocorrido_em' => now(),
            ]);
        }

        return $usuario;
    }

    /**
     * O ator so age sobre alvo com ao menos um vinculo vigente, todos cobertos
     * pela sua subarvore e com papel dentro da sua autoridade.
     */
    private function garantirAutoridadeSobreUsuario(ContextoAtuacao $contexto, Usuario $usuario): void
    {
        $vinculos = $usuario->vinculosVigentes()->with('papel')->get();

        if ($vinculos->isEmpty()) {
            throw new ProblemException('usuario_fora_escopo', 'Acesso negado', 403, 'Usuário fora da sua autoridade.');
        }

        foreach ($vinculos as $vinculo) {
            /** @var VinculoAcesso $vinculo */
            if (! $this->autoridade->podeSobrePapel($contexto, (string) $vinculo->papel?->codigo)
                || ! $this->autoridade->cobre($contexto, (string) $vinculo->tipo_escopo, (string) $vinculo->escopo_id)) {
                throw new ProblemException('usuario_fora_escopo', 'Acesso negado', 403, 'Usuário fora da sua autoridade.');
            }
        }
    }
}
