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
     * Busca candidatos por nome normalizado + data de nascimento + igreja de
     * referencia (dedup, CA-27). Devolve apenas o sinal.
     *
     * @return array<int, Participante>
     */
    public function buscar(string $nome, ?string $dataNascimento = null, ?string $igrejaReferenciaId = null): array;

    /**
     * Corrige dados cadastrais permitidos. Registra auditoria com o estado
     * anterior para reversao (ADR-0007).
     *
     * @param  array<string, mixed>  $dados
     */
    public function corrigir(string $usuarioId, string $id, array $dados): Participante;
}
