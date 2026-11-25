<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Equipe coerente por padrao: igreja, EBI filho dela e uma coordenadora com
 * vinculo vigente no EBI.
 *
 * @extends Factory<Equipe>
 */
class EquipeFactory extends Factory
{
    protected $model = Equipe::class;

    public function definition(): array
    {
        $igreja = Igreja::factory()->create();
        $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id]);

        return [
            'ebi_id' => $ebi->id,
            'igreja_id' => $igreja->id,
            'coordenadora_id' => $this->coordenadoraVigente($ebi->id),
            'criado_por' => null,
        ];
    }

    private function coordenadoraVigente(string $ebiId): string
    {
        $usuario = Usuario::factory()->create();

        VinculoAcesso::query()->create([
            'usuario_id' => $usuario->id,
            'papel_id' => Papel::query()->where('codigo', 'coordenadora')->value('id'),
            'tipo_escopo' => 'ebi',
            'escopo_id' => $ebiId,
            'inicio' => now()->subMonth()->toDateString(),
            'fim' => null,
            'status' => 'ativo',
        ]);

        return (string) $usuario->id;
    }
}
