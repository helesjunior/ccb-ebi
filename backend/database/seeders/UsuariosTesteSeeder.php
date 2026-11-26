<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\CentralNacional;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Organizacao\Models\Regional;
use App\Domain\Organizacao\Models\Setor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Um usuário de teste por nível de acesso (dev/demonstração).
 *
 * Serve para exercitar todos os fluxos pela interface. Todos usam a senha
 * `senha123` e o MESMO segredo TOTP de demonstração (MfaDemoSeeder::SEGREDO),
 * já confirmado: basta cadastrar o segredo uma vez no app autenticador.
 *
 * Escopos: a organização do seed é o caminho completo (central -> regional ->
 * setor -> igreja -> EBI), então cada usuário territorial cobre toda a
 * hierarquia abaixo dele.
 *
 * Idempotente: pode rodar repetidas vezes.
 */
class UsuariosTesteSeeder extends Seeder
{
    public const SENHA = 'senha123';

    /** @var list<array{email:string,nome:string,papel:string,tipo:string,escopo:class-string}> */
    private const USUARIOS = [
        ['super@ebi.test', 'Super administrador (teste)', 'super_admin', VinculoAcesso::TIPO_CENTRAL, CentralNacional::class],
        ['regional@ebi.test', 'Administrador regional (teste)', 'adm_regional', VinculoAcesso::TIPO_REGIONAL, Regional::class],
        ['setor@ebi.test', 'Administrador de setor (teste)', 'adm_setor', VinculoAcesso::TIPO_SETOR, Setor::class],
        ['igreja@ebi.test', 'Administrador de igreja (teste)', 'adm_igreja', VinculoAcesso::TIPO_IGREJA, Igreja::class],
        ['ministerio@ebi.test', 'Ministério local (teste)', 'ministerio_local', VinculoAcesso::TIPO_EBI, Ebi::class],
        ['chefe@ebi.test', 'Coordenadora chefe (teste)', 'coordenadora_chefe', VinculoAcesso::TIPO_EBI, Ebi::class],
        ['coordenadora2@ebi.test', 'Coordenadora 2 (teste)', 'coordenadora', VinculoAcesso::TIPO_EBI, Ebi::class],
        ['auxiliar@ebi.test', 'Auxiliar (teste)', 'auxiliar', VinculoAcesso::TIPO_EBI, Ebi::class],
        ['auxiliar2@ebi.test', 'Auxiliar 2 (teste)', 'auxiliar', VinculoAcesso::TIPO_EBI, Ebi::class],
        ['responsavel@ebi.test', 'Responsável (teste)', 'responsavel', VinculoAcesso::TIPO_EBI, Ebi::class],
    ];

    public function run(): void
    {
        // A coordenadora chefe é única por EBI (índice único em chefe_de_ebi_id):
        // o usuário de demonstração original cede a vaga.
        Usuario::query()
            ->where('email_normalizado', 'coordenadora@ebi.test')
            ->first()?->vinculosVigentes()
            ->whereRelation('papel', 'codigo', VinculoAcesso::PAPEL_CHEFE)
            ->update(['fim' => now()->toDateString(), 'chefe_de_ebi_id' => null]);

        foreach (self::USUARIOS as [$email, $nome, $papelCodigo, $tipo, $modelo]) {
            $usuario = Usuario::query()->updateOrCreate(
                ['email_normalizado' => Usuario::emailNormalizado($email)],
                [
                    'nome' => $nome,
                    'email_verificado_em' => now(),
                    'senha' => Hash::make(self::SENHA),
                    'status' => 'ativo',
                ],
            );

            // MFA obrigatório para todo papel diferente de `responsavel`.
            FatorMfa::query()->updateOrCreate(
                ['usuario_id' => $usuario->id, 'tipo' => FatorMfa::TIPO_TOTP],
                ['segredo_cifrado' => MfaDemoSeeder::SEGREDO, 'confirmado_em' => now()],
            );

            $papelId = Papel::query()->where('codigo', $papelCodigo)->value('id');
            $escopoId = $modelo::query()->orderBy('id')->value('id');

            if ($papelId === null || $escopoId === null) {
                continue; // sem catálogo ou sem organização, nada a vincular
            }

            VinculoAcesso::query()->updateOrCreate(
                [
                    'usuario_id' => $usuario->id,
                    'papel_id' => $papelId,
                    'tipo_escopo' => $tipo,
                    'escopo_id' => $escopoId,
                ],
                ['inicio' => now()->toDateString(), 'fim' => null, 'status' => 'ativo'],
            );
        }
    }
}
