<?php

namespace Database\Seeders;

use App\Domain\Identidade\Models\FatorMfa;
use App\Domain\Identidade\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Segundo fator (TOTP) pré-inscrito para a demonstração (E03/dev).
 *
 * A coordenadora tem papel que exige MFA (`GerenciadorMfa::exige`), então o
 * login pela interface sempre pede o segundo fator — e um usuário novo não tem
 * fator inscrito. Este seeder grava um fator TOTP **já confirmado** com um
 * segredo **fixo e conhecido**, para o segredo poder ser adicionado a um app
 * autenticador uma única vez e o login ser testável na interface.
 *
 * Segredo de desenvolvimento (base32, RFC 4648). NÃO usar fora de dev.
 *
 * Idempotente: pode rodar repetidas vezes.
 */
class MfaDemoSeeder extends Seeder
{
    /** Segredo TOTP fixo de demonstração (base32, sem padding). */
    public const SEGREDO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function run(): void
    {
        $coordenadora = Usuario::query()
            ->where('email_normalizado', 'coordenadora@ebi.test')
            ->first();

        if ($coordenadora === null) {
            return; // sem usuário de demonstração, nada a fazer
        }

        FatorMfa::query()->updateOrCreate(
            ['usuario_id' => $coordenadora->id, 'tipo' => FatorMfa::TIPO_TOTP],
            ['segredo_cifrado' => self::SEGREDO, 'confirmado_em' => now()],
        );
    }
}
