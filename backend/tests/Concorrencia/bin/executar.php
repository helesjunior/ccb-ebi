<?php

/**
 * Executa UMA operacao de escala (ou de presenca) num processo proprio (concorrencia real:
 * conexao de banco independente). Chamado por tests/Concorrencia/Suporte.php.
 *
 * Uso: php executar.php <acao> <id> <alvo> <usuarioId> <papel> <escopo>
 *   - escolher: id = oferta,  alvo ignorado
 *   - cancelar: id = vaga,    alvo ignorado
 *   - retirar:  id = membro,  alvo ignorado
 *   - incluir:  id = escala,  alvo = usuario incluido (funcao auxiliar)
 *   - decidir:  id = troca,   alvo = substituto
 *   - contingencia: id = sessao, alvo = participantes separados por virgula
 *   escopo = `ebi:<id>`.
 *
 * Imprime `ok` ou `code:<problema>` na primeira linha.
 */

use App\Domain\Escalas\Contracts\GerirEscala;
use App\Domain\Escalas\Contracts\GerirTrocaEscala;
use App\Domain\Escalas\Contracts\GerirVagaEscala;
use App\Domain\Identidade\Contracts\ContextoDecisao;
use App\Domain\Sessoes\Contracts\RegistrarPresenca;
use App\Support\ProblemException;
use Illuminate\Contracts\Console\Kernel;

if (getenv('TESTE_PGSQL') !== '1') {
    fwrite(STDERR, "TESTE_PGSQL=1 e obrigatorio.\n");
    exit(2);
}

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $acao, $id, $alvo, $usuarioId, $papel, $escopo] = $argv;

[$tipo, $escopoId] = array_pad(explode(':', $escopo, 2), 2, '');

$contexto = new ContextoDecisao(
    usuarioId: $usuarioId,
    papelCodigo: $papel,
    tipoEscopo: $tipo,
    escopoId: $escopoId,
);

try {
    match ($acao) {
        'escolher' => $app->make(GerirVagaEscala::class)->escolher($id, $contexto, $usuarioId),
        'cancelar' => $app->make(GerirVagaEscala::class)->cancelar($id, $contexto, $usuarioId),
        'retirar' => $app->make(GerirEscala::class)->retirarMembro($id, $contexto, $usuarioId),
        'incluir' => $app->make(GerirEscala::class)->incluirMembro($id, $alvo, 'auxiliar', $contexto, $usuarioId),
        'decidir' => $app->make(GerirTrocaEscala::class)->decidir($id, 'aprovar', $alvo, null, $contexto, $usuarioId),
        'contingencia' => $app->make(RegistrarPresenca::class)->registrarContingenciaLote($id, explode(',', $alvo), 'lista em papel', $usuarioId),
        default => throw new RuntimeException("Acao desconhecida: {$acao}"),
    };

    echo "ok\n";
} catch (ProblemException $e) {
    echo 'code:'.$e->problemCode."\n";
}
