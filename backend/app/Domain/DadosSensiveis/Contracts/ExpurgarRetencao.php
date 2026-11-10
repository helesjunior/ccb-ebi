<?php

namespace App\Domain\DadosSensiveis\Contracts;

use App\Domain\DadosSensiveis\Support\ResultadoRetencao;

/**
 * Aplica os prazos de retenção aos dados sensíveis do participante.
 *
 * O serviço honra apenas o parâmetro `$executar`: quando falso, nada é escrito —
 * apenas um relatório é devolvido. O "desligado por padrão"
 * (`config('privacidade.retencao.ativa')` falso, até o DPO aprovar — RNF-07) é
 * responsabilidade do **comando** (`privacidade:expurgar`), que só executa de
 * fato com `--force`; o serviço em si não lê essa config.
 */
interface ExpurgarRetencao
{
    /**
     * Expurga (descarta/anonimiza) os dados sensíveis vencidos.
     *
     * Para cada registro vencido: marca `expurgado_em`, zera `conteudo_cifrado`
     * e `referencia_segura` (anonimização) e grava um evento de auditoria
     * (`acao=retencao.expurgar`, `origem=sistema`, `ator_id=null`). Registros já
     * expurgados (`expurgado_em` preenchido) não são reauditados (idempotência).
     *
     * @param  bool  $executar  Quando falso, apenas conta candidatos sem escrever.
     * @return ResultadoRetencao Contagens de candidatos/expurgados e se executou.
     */
    public function executar(bool $executar = false): ResultadoRetencao;
}
