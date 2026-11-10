<?php

namespace App\Domain\Identidade\Services;

use App\Domain\Identidade\Contracts\RegistrarConta;
use App\Domain\Identidade\Contracts\VerificarEmail;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Identidade\Notifications\VerificacaoEmail;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Support\EvidenciaAceite;
use App\Support\ProblemException;
use Illuminate\Support\Facades\DB;

class GerenciadorConta implements RegistrarConta, VerificarEmail
{
    public function __construct(
        private readonly ConsultarHierarquia $hierarquia,
        private readonly GerirTermos $termos,
        private readonly RegistrarConsentimento $consentimentos,
    ) {}

    public function registrar(
        string $nome,
        string $email,
        string $senha,
        string $termoVersaoId,
        string $origem,
        EvidenciaAceite $evidencia,
    ): Usuario {
        $normalizado = Usuario::emailNormalizado($email);

        if (Usuario::query()->where('email_normalizado', $normalizado)->exists()) {
            throw new ProblemException(
                'email_em_uso',
                'E-mail indisponível',
                422,
                'Já existe uma conta com este e-mail.',
            );
        }

        // CA-16: so o termo de cadastro vigente pode ser aceito no cadastro.
        $vigente = $this->termos->vigente('cadastro');

        if ($vigente === null || $vigente->id !== $termoVersaoId) {
            throw new ProblemException(
                'termo_indisponivel',
                'Termo indisponível',
                422,
                'A versão de termo informada não é a vigente para o cadastro.',
            );
        }

        return DB::transaction(function () use ($nome, $normalizado, $senha, $termoVersaoId, $origem, $evidencia): Usuario {
            $usuario = Usuario::query()->create([
                'nome' => $nome,
                'email_normalizado' => $normalizado,
                'email_verificado_em' => null,
                'senha' => $senha,
                'status' => 'ativo',
            ]);

            $papelResponsavel = Papel::query()->where('codigo', 'responsavel')->value('id')
                ?? throw new ProblemException('config_ausente', 'Configuração ausente', 500, 'Papel responsavel nao cadastrado.');

            VinculoAcesso::query()->create([
                'usuario_id' => $usuario->id,
                'papel_id' => $papelResponsavel,
                'tipo_escopo' => VinculoAcesso::TIPO_CENTRAL,
                'escopo_id' => $this->hierarquia->garantirCentralId(),
                'inicio' => now()->toDateString(),
                'fim' => null,
                'status' => 'ativo',
            ]);

            // Consentimento na mesma transacao do cadastro (CA-16).
            $this->consentimentos->registrar(
                $usuario->id,
                $termoVersaoId,
                'aceito',
                $origem,
                $evidencia,
            );

            $usuario->notify(new VerificacaoEmail);

            return $usuario;
        });
    }

    public function verificar(string $usuarioId): void
    {
        $usuario = Usuario::query()->find($usuarioId);

        if ($usuario === null) {
            throw new ProblemException('token_invalido', 'Token inválido', 422, 'Usuário não encontrado.');
        }

        if ($usuario->email_verificado_em === null) {
            $usuario->forceFill(['email_verificado_em' => now()])->save();
        }
    }

    public function reenviar(string $email): void
    {
        $usuario = Usuario::query()
            ->where('email_normalizado', Usuario::emailNormalizado($email))
            ->first();

        if ($usuario !== null && $usuario->email_verificado_em === null) {
            $usuario->notify(new VerificacaoEmail);
        }
    }
}
