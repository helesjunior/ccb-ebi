<?php

namespace App\Domain\DadosSensiveis\Contracts;

/**
 * Lê um dado sensível de participante, aplicando controle de acesso e
 * registrando o evento na trilha de auditoria.
 *
 * Toda leitura gera um evento `dado_sensivel.ler` (append-only). Não existe
 * leitura anônima: um ator identificado é obrigatório. Nenhum conteúdo
 * sensível é gravado na auditoria — apenas tipo, finalidade e id do dado.
 */
interface LerDadoSensivel
{
    /**
     * Decifra e devolve o conteúdo sensível, ou a referência segura quando não
     * houver conteúdo cifrado.
     *
     * @param  string  $id  Identificador do dado sensível.
     * @param  string  $atorId  Identificador do ator que lê (obrigatório).
     * @param  string  $papelAtuante  Papel do ator, validado contra o controle de acesso.
     * @param  string|null  $escopo  Escopo territorial da atuação (ex.: `igreja:1`).
     */
    public function ler(string $id, string $atorId, string $papelAtuante, ?string $escopo = null): string;
}
