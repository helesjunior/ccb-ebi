<?php

namespace App\Domain\Escalas\Database\Factories;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Sessoes\Models\Sessao;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Escala> */
class EscalaFactory extends Factory
{
    protected $model = Escala::class;

    public function definition(): array
    {
        // `sessao_id` e unico: cada escala nova recebe a sua sessao, com o
        // mesmo EBI e a mesma igreja.
        $sessao = Sessao::factory();

        return [
            'sessao_id' => $sessao,
            'ebi_id' => fn (array $atributos): string => (string) $this->ebiDaSessao($atributos['sessao_id']),
            'igreja_id' => fn (array $atributos): string => (string) $this->igrejaDaSessao($atributos['sessao_id']),
            // A escala nasce da equipe de uma coordenadora do EBI (criada se o
            // teste nao informar a equipe).
            'equipe_id' => fn (array $atributos): string => $this->equipeDoEbi(
                $atributos['ebi_id'], $atributos['igreja_id'], $atributos['criado_por'] ?? null,
            ),
            'estimativa_participantes' => 20,
            'participantes_por_auxiliar' => 10,
            'status' => Escala::STATUS_PREVISTA,
            'criado_por' => (string) str()->ulid(),
        ];
    }

    private function ebiDaSessao(string $sessaoId): string
    {
        return (string) Sessao::query()->whereKey($sessaoId)->value('ebi_id');
    }

    private function igrejaDaSessao(string $sessaoId): string
    {
        return (string) Sessao::query()->whereKey($sessaoId)->value('igreja_id');
    }

    /** Equipe existente do EBI (`criado_por` como dona) ou uma nova. */
    private function equipeDoEbi(string $ebiId, string $igrejaId, ?string $criadoPor): string
    {
        $existente = Equipe::query()->where('ebi_id', $ebiId)->value('id');

        if ($existente !== null) {
            return (string) $existente;
        }

        $coordenadoraId = $this->coordenadoraVigente($ebiId);

        return (string) Equipe::query()->create([
            'ebi_id' => $ebiId,
            'igreja_id' => $igrejaId,
            'coordenadora_id' => $coordenadoraId,
            'criado_por' => $criadoPor ?? $coordenadoraId,
        ])->id;
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
