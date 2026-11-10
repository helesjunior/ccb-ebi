<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacidade (RF-04): o modulo nasce inteiro, entao as tres tabelas ficam
 * juntas. Cada versao de termo guarda o hash do texto efetivamente aceito;
 * manifestacoes e solicitacoes do titular apontam para ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('termo_versao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('finalidade');
            $table->string('base_legal');
            $table->string('versao');
            $table->text('texto');
            $table->string('texto_hash', 64);
            $table->timestamp('vigente_em');
            $table->timestamp('encerrado_em')->nullable();
            $table->timestamps();

            $table->unique(['finalidade', 'versao']);
            $table->index(['finalidade', 'vigente_em']);
        });

        Schema::create('manifestacao_privacidade', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->nullable()->constrained('usuarios');
            $table->string('participante_id')->nullable(); // FK real entra no E03
            $table->foreignUlid('termo_versao_id')->constrained('termo_versao');
            $table->string('decisao');   // aceito | recusado | retirado
            $table->string('origem');    // proprio | assistido
            $table->text('evidencia');
            $table->timestamp('ocorrido_em');
            $table->timestamps();

            $table->index(['usuario_id', 'termo_versao_id']);
        });

        Schema::create('solicitacao_titular', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('solicitante_id')->constrained('usuarios');
            $table->string('participante_id')->nullable();
            $table->string('tipo');        // acesso | correcao | revogacao
            $table->string('protocolo')->unique();
            $table->foreignUlid('responsavel_id')->nullable()->constrained('usuarios');
            $table->date('prazo')->nullable();
            $table->string('status');      // recebida | em_analise | atendida | recusada
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_titular');
        Schema::dropIfExists('manifestacao_privacidade');
        Schema::dropIfExists('termo_versao');
    }
};
