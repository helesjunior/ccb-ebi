<?php

namespace App\Domain\Participantes\Contracts;

use App\Domain\Participantes\Models\Participante;

/**
 * Superficie publica de escrita/consulta de participantes (RF-02). Outros
 * modulos (e a camada HTTP) usam por aqui, nunca lendo a tabela diretamente
 * (ADR-0002).
 */
interface GerirParticipante
{
    /**
     * Cadastra um participante novo (origem `responsavel`). Gera a matricula
     * nacional e registra auditoria.
     *
     * @param  array<string, mixed>  $dados  nome, data_nascimento, nome_social?, igreja_referencia_id?
     */
    public function cadastrar(string $usuarioId, array $dados): Participante;

    public function encontrar(string $id): ?Participante;

    /**
     * Cadastro assistido (FL-01, CA-16): a coordenadora cadastra um participante
     * com uma evidencia de aceite obrigatoria (arquivo, ja no pipeline). Cria o
     * participante (origem `assistido`), o responsavel sem conta, o arquivo de
     * evidencia e a manifestacao de consentimento (usuario nulo).
     *
     * @param  array<string, mixed>  $dados  nome, data_nascimento, nome_social?, responsavel_nome, tipo_relacao
     */
    public function cadastrarAssistido(string $usuarioId, array $dados, string $evidenciaArquivoId): Participante;

    /**
     * Busca candidatos por nome normalizado + data de nascimento + igreja de
     * referencia (dedup, CA-27). Devolve apenas o sinal.
     *
     * @return array<int, Participante>
     */
    public function buscar(string $nome, ?string $dataNascimento = null, ?string $igrejaReferenciaId = null): array;

    /**
     * Corte de 5 da decisao 11 da spec, sobre os candidatos ja autorizados:
     * grupos de 1 a 4 ativos por igreja de referencia (e quem nao tem igreja
     * de referencia) saem da resposta.
     *
     * @param  array<int, Participante>  $candidatos
     * @return array{0: array<int, Participante>, 1: bool} candidatos e se algo foi retirado
     */
    public function cortarVisiveis(array $candidatos, bool $suprimir = true): array;

    /**
     * Corrige dados cadastrais permitidos. Registra auditoria com o estado
     * anterior para reversao (ADR-0007).
     *
     * @param  array<string, mixed>  $dados
     */
    public function corrigir(string $usuarioId, string $id, array $dados): Participante;
}
