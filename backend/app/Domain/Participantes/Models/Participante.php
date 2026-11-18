<?php

namespace App\Domain\Participantes\Models;

use App\Domain\Participantes\Database\Factories\ParticipanteFactory;
use App\Domain\Participantes\Exceptions\MatriculaEsgotadaException;
use App\Domain\Participantes\Services\GeradorMatricula;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class Participante extends Model
{
    /** @use HasFactory<ParticipanteFactory> */
    use HasFactory;

    use HasUlids;

    public const ORIGEM_RESPONSAVEL = 'responsavel';

    public const ORIGEM_ASSISTIDO = 'assistido';

    public const STATUS_ATIVO = 'ativo';

    protected $table = 'participante';

    protected $fillable = [
        'matricula',
        'nome',
        'nome_social',
        'data_nascimento',
        'status',
        'igreja_referencia_id',
        'origem_cadastro',
        'cadastrado_por',
        'evidencia_consentimento_id',
        'snapshot_mesclagem',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
        'snapshot_mesclagem' => 'array',
    ];

    protected $hidden = ['nome_busca'];

    protected static function booted(): void
    {
        static::saving(function (Participante $participante): void {
            $participante->nome_busca = mb_strtolower(Str::ascii((string) $participante->nome));
        });
    }

    /** @return HasMany<ResponsavelParticipante, $this> */
    public function responsaveis(): HasMany
    {
        return $this->hasMany(ResponsavelParticipante::class);
    }

    /** @return HasMany<ExcecaoEtaria, $this> */
    public function excecoes(): HasMany
    {
        return $this->hasMany(ExcecaoEtaria::class);
    }

    public function excecaoVigente(): ?ExcecaoEtaria
    {
        return $this->excecoes()
            ->where('status', ExcecaoEtaria::STATUS_APROVADA)
            ->orderByDesc('decidido_em')
            ->first();
    }

    /**
     * Cria um participante com a matricula gerada e retry em colisao (CA-25):
     * o indice `unique` e a garantia real; aqui so tratamos a corrida rara.
     *
     * @param  array<string, mixed>  $atributos
     */
    public static function criarComMatricula(array $atributos): self
    {
        $gerador = app(GeradorMatricula::class);

        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            try {
                return self::create([...$atributos, 'matricula' => $gerador->gerar()]);
            } catch (QueryException $e) {
                if (! self::violacaoDeMatricula($e)) {
                    throw $e;
                }
            }
        }

        throw new MatriculaEsgotadaException;
    }

    private static function violacaoDeMatricula(QueryException $e): bool
    {
        $mensagem = strtolower($e->getMessage());

        // Postgres: `duplicate key value violates unique constraint
        // "participante_matricula_unique"`. SQLite: `UNIQUE constraint failed:
        // participante.matricula`. Exigimos o rotulo de unicidade E o nome da
        // coluna, para nao confundir com NOT NULL de outro campo.
        $unica = str_contains($mensagem, 'unique') || str_contains($mensagem, 'duplicate');

        return $unica && str_contains($mensagem, 'matricula');
    }

    protected static function newFactory(): ParticipanteFactory
    {
        return ParticipanteFactory::new();
    }
}
