<?php

namespace App\Domain\Privacidade\Models;

use App\Domain\Privacidade\Database\Factories\SolicitacaoTitularFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Solicitacao de exercicio de direitos do titular (RF-16): acesso, correcao
 * ou revogacao, com protocolo proprio e ciclo de atendimento.
 */
class SolicitacaoTitular extends Model
{
    /** @use HasFactory<SolicitacaoTitularFactory> */
    use HasFactory;

    use HasUlids;

    public const TIPO_ACESSO = 'acesso';

    public const TIPO_CORRECAO = 'correcao';

    public const TIPO_REVOGACAO = 'revogacao';

    public const STATUS_RECEBIDA = 'recebida';

    protected $table = 'solicitacao_titular';

    protected $fillable = [
        'solicitante_id',
        'participante_id',
        'tipo',
        'protocolo',
        'responsavel_id',
        'prazo',
        'status',
        'descricao',
    ];

    protected $casts = [
        'prazo' => 'date',
    ];

    protected static function newFactory(): SolicitacaoTitularFactory
    {
        return SolicitacaoTitularFactory::new();
    }
}
