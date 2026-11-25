<?php

namespace Tests\Concorrencia;

use App\Domain\Escalas\Models\Equipe;
use App\Domain\Escalas\Models\Escala;
use App\Domain\Escalas\Models\EscalaMembro;
use App\Domain\Escalas\Models\OfertaVagaEscala;
use App\Domain\Escalas\Models\SolicitacaoTrocaEscala;
use App\Domain\Escalas\Models\VagaEscala;
use App\Domain\Identidade\Models\Papel;
use App\Domain\Identidade\Models\Usuario;
use App\Domain\Identidade\Models\VinculoAcesso;
use App\Domain\Organizacao\Models\Ebi;
use App\Domain\Organizacao\Models\Igreja;
use App\Domain\Sessoes\Models\Sessao;
use Database\Seeders\PapelSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

/**
 * Cenario e processos dos testes de concorrencia. Cada processo abre a propria
 * conexao com o PostgreSQL e chama o servico real por bin/executar.php.
 */
final class Suporte
{
    /**
     * Dispara os processos ANTES de ler qualquer saida: sem isso o primeiro
     * terminaria sozinho e nada seria concorrente.
     *
     * @param  array{0:string,1:string,2:string,3:string,4:string,5:string}  $a
     * @param  array{0:string,1:string,2:string,3:string,4:string,5:string}  $b
     * @return array{0:string,1:string}
     */
    public static function correr(array $a, array $b): array
    {
        $processos = array_map(self::processo(...), [$a, $b]);

        foreach ($processos as $processo) {
            $processo->start();
        }

        return array_map(function (Process $processo): string {
            $processo->wait();

            $saida = trim((string) strtok((string) $processo->getOutput(), "\n"));

            if ($saida === 'ok' || str_starts_with($saida, 'code:')) {
                return $saida;
            }

            // Falha de verdade: a primeira linha util costuma ser o cabecalho
            // ("In Connection.php line 864:"), entao procuramos a causa.
            $erro = $processo->getErrorOutput()."\n".$processo->getOutput();

            foreach (explode("\n", $erro) as $linha) {
                if (str_contains($linha, 'SQLSTATE')) {
                    return 'erro:'.trim($linha);
                }
            }

            return 'erro:'.trim($processo->getErrorOutput() ?: $processo->getOutput());
        }, $processos);
    }

    /** @param  array{0:string,1:string,2:string,3:string,4:string,5:string}  $args */
    private static function processo(array $args): Process
    {
        return new Process(
            [PHP_BINARY, __DIR__.'/bin/executar.php', ...$args],
            __DIR__.'/../..',
            [
                'TESTE_PGSQL' => '1',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => 'ebi_teste',
            ],
            null,
            180,
        );
    }

    /**
     * Banco `ebi_teste` limpo com uma escala aprovada (dona, original e dois
     * auxiliares de fora da escala) pronta para os pares de corrida.
     *
     * @return array<string, mixed>
     */
    public static function cenario(): array
    {
        Artisan::call('migrate:fresh', ['--force' => true, '--seed' => false]);
        app(PapelSeeder::class)->run();

        $igreja = Igreja::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $ebi = Ebi::factory()->create(['igreja_id' => $igreja->id, 'participantes_por_auxiliar' => 4]);
        $escopo = 'ebi:'.$ebi->id;

        $sessao = Sessao::factory()->create([
            'ebi_id' => $ebi->id, 'igreja_id' => $igreja->id,
            'inicio_previsto' => '2027-01-09 23:00:00', 'fim_previsto' => '2027-01-10 01:00:00',
            'status' => Sessao::STATUS_PREVISTA,
        ]);

        $dona = self::pessoa('coordenadora', 'ebi', (string) $ebi->id);
        $equipe = Equipe::factory()->create([
            'ebi_id' => $ebi->id, 'igreja_id' => $igreja->id,
            'coordenadora_id' => $dona->id, 'criado_por' => $dona->id,
        ]);

        $original = self::pessoa('auxiliar', 'ebi', (string) $ebi->id);
        $ofertante = self::pessoa('auxiliar', 'ebi', (string) $ebi->id);
        $outroOfertante = self::pessoa('auxiliar', 'ebi', (string) $ebi->id);
        $substitutoDaTroca = self::pessoa('auxiliar', 'ebi', (string) $ebi->id);

        $escala = Escala::factory()->create([
            'sessao_id' => $sessao->id, 'equipe_id' => $equipe->id,
            'ebi_id' => $ebi->id, 'igreja_id' => $igreja->id,
            'estimativa_participantes' => 8, 'participantes_por_auxiliar' => 4,
            'status' => Escala::STATUS_APROVADA,
        ]);

        EscalaMembro::factory()->for($escala)->create([
            'usuario_id' => $dona->id, 'funcao' => EscalaMembro::FUNCAO_COORDENADORA,
            'status' => EscalaMembro::STATUS_CONFIRMADO,
        ]);
        $membroOriginal = EscalaMembro::factory()->for($escala)->create([
            'usuario_id' => $original->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
            'status' => EscalaMembro::STATUS_CONFIRMADO,
        ]);

        // Substituicao do original: a vaga disputada pelo par "aceite x ...".
        $vagaOriginal = VagaEscala::query()->create([
            'escala_id' => $escala->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
            'substitui_membro_id' => $membroOriginal->id,
            'substituido_usuario_id' => $original->id,
            'status' => VagaEscala::STATUS_ABERTA, 'aberta_por' => $dona->id,
        ]);
        $ofertaOriginal = OfertaVagaEscala::query()->create([
            'vaga_id' => $vagaOriginal->id, 'usuario_id' => $ofertante->id,
            'status' => OfertaVagaEscala::STATUS_PENDENTE,
        ]);
        $trocaDoOriginal = SolicitacaoTrocaEscala::query()->create([
            'escala_membro_id' => $membroOriginal->id,
            'solicitante_id' => $original->id,
            'status' => SolicitacaoTrocaEscala::STATUS_PENDENTE,
        ]);

        // Acrescimo com duas ofertas: "dois aceites" e "aceite x inclusao".
        $vagaAcrescimo = VagaEscala::query()->create([
            'escala_id' => $escala->id, 'funcao' => EscalaMembro::FUNCAO_AUXILIAR,
            'substitui_membro_id' => null,
            'status' => VagaEscala::STATUS_ABERTA, 'aberta_por' => $dona->id,
        ]);
        $ofertaAcrescimo = OfertaVagaEscala::query()->create([
            'vaga_id' => $vagaAcrescimo->id, 'usuario_id' => $ofertante->id,
            'status' => OfertaVagaEscala::STATUS_PENDENTE,
        ]);
        $ofertaAcrescimoOutra = OfertaVagaEscala::query()->create([
            'vaga_id' => $vagaAcrescimo->id, 'usuario_id' => $outroOfertante->id,
            'status' => OfertaVagaEscala::STATUS_PENDENTE,
        ]);

        return [
            'escopo' => $escopo,
            'escala' => $escala,
            'dona' => $dona,
            'original' => $membroOriginal,
            'originalUsuario' => $original,
            'trocaDoOriginal' => $trocaDoOriginal,
            'substitutoDaTroca' => $substitutoDaTroca,
            'vagaOriginal' => $vagaOriginal,
            'ofertaOriginal' => $ofertaOriginal,
            'ofertante' => $ofertante,
            'vagaAcrescimo' => $vagaAcrescimo,
            'ofertaAcrescimo' => $ofertaAcrescimo,
            'ofertaAcrescimoOutra' => $ofertaAcrescimoOutra,
            'outroOfertante' => $outroOfertante,
        ];
    }

    private static function pessoa(string $papel, string $tipo, string $escopoId): Usuario
    {
        $usuario = Usuario::factory()->create([
            'senha' => Hash::make('senha-bem-longa-123'),
            'email_verificado_em' => now(),
        ]);

        VinculoAcesso::factory()->create([
            'usuario_id' => $usuario->id,
            'papel_id' => Papel::query()->where('codigo', $papel)->value('id'),
            'tipo_escopo' => $tipo,
            'escopo_id' => $escopoId,
            'inicio' => now()->subMonth()->toDateString(),
        ]);

        return $usuario;
    }

    public static function membro(string $membroId): ?EscalaMembro
    {
        return EscalaMembro::query()->find($membroId);
    }

    public static function statusDaVaga(string $vagaId): string
    {
        return (string) VagaEscala::query()->whereKey($vagaId)->value('status');
    }

    public static function statusDaOferta(string $ofertaId): string
    {
        return (string) OfertaVagaEscala::query()->whereKey($ofertaId)->value('status');
    }

    public static function statusDaTroca(string $trocaId): string
    {
        return (string) SolicitacaoTrocaEscala::query()->whereKey($trocaId)->value('status');
    }

    /** Quantos membros ativos (nao substituidos) a pessoa tem nesta escala. */
    public static function membrosAtivos(string $escalaId, string $usuarioId): int
    {
        return EscalaMembro::query()
            ->where('escala_id', $escalaId)
            ->where('usuario_id', $usuarioId)
            ->where('status', '!=', EscalaMembro::STATUS_SUBSTITUIDO)
            ->count();
    }

    /** O membro criado pelo aceite da oferta, se houver. */
    public static function membroDaOferta(string $ofertaId): ?EscalaMembro
    {
        $membroId = OfertaVagaEscala::query()->whereKey($ofertaId)->value('membro_criado_id');

        return $membroId === null ? null : EscalaMembro::query()->find($membroId);
    }
}
