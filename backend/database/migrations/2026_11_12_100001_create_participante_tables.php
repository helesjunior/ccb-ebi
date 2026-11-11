<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E03 (RF-02, RF-03): o participante e seus vinculos. Nasce inteiro, entao as
 * quatro tabelas ficam juntas. Completa a FK real de `participante_id` que a
 * E05 deixou pendente em `manifestacao_privacidade` e `dado_sensivel_participante`.
 *
 * Semantica de vinculo (ADR-0018, ADR-0027):
 * - `responsavel_participante` e a situacao corrente (uma linha por par);
 *   o gate de acesso e `validacao = aprovada` (CA-04).
 * - `solicitacao_vinculo` e o log append-only de pedidos/contestacoes/revogacoes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participante', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('matricula', 9)->unique();
            $table->string('nome');
            $table->string('nome_social')->nullable();
            $table->date('data_nascimento');
            $table->string('status')->default('ativo');
            $table->string('igreja_referencia_id')->nullable();
            $table->string('origem_cadastro');       // responsavel | assistido
            $table->string('cadastrado_por')->nullable();
            $table->string('evidencia_consentimento_id')->nullable();
            $table->json('snapshot_mesclagem')->nullable(); // estado anterior p/ desfazer (ADR-0007)
            $table->timestamps();

            $table->index('nome');
            $table->index('igreja_referencia_id');
            $table->index('origem_cadastro');
        });

        Schema::create('responsavel_participante', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->nullable()->constrained('usuarios'); // responsavel sem conta
            $table->string('nome');
            $table->string('telefone')->nullable();
            $table->foreignUlid('participante_id')->constrained('participante');
            $table->string('tipo_relacao');
            $table->json('permissoes')->nullable();
            $table->string('restricoes')->nullable();
            $table->string('status')->default('ativo');
            $table->string('validacao')->default('pendente'); // pendente|aprovada|aguardando_documento|recusada
            $table->timestamp('conferido_em')->nullable();
            $table->string('conferido_por')->nullable();
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->timestamps();

            $table->index(['participante_id', 'validacao']);
            $table->index('usuario_id');
        });

        Schema::create('solicitacao_vinculo', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->constrained('usuarios');
            $table->foreignUlid('participante_id')->constrained('participante');
            $table->foreignUlid('responsavel_participante_id')->nullable()->constrained('responsavel_participante');
            $table->string('tipo');   // solicitacao | contestacao | revogacao
            $table->string('status')->default('pendente');
            $table->string('decidido_por')->nullable();
            $table->timestamp('decidido_em')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['participante_id', 'tipo']);
        });

        Schema::create('excecao_etaria', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('participante_id')->constrained('participante');
            $table->string('tipo_necessidade');
            $table->text('apoios_necessarios')->nullable();
            $table->text('justificativa_funcional')->nullable();
            $table->string('status')->default('solicitada'); // solicitada|aprovada|recusada|suspensa|revogada|expirada
            $table->string('solicitado_por')->nullable();
            $table->string('decidido_por')->nullable();
            $table->foreignUlid('responsavel_concordante_id')->nullable()->constrained('responsavel_participante');
            $table->timestamp('decidido_em')->nullable();
            $table->date('vigente_ate')->nullable();
            $table->date('revisao_prevista_em')->nullable();
            $table->timestamp('revogado_em')->nullable();
            $table->text('motivo')->nullable();
            $table->timestamps();

            $table->index(['participante_id', 'status']);
        });

        // E03 fecha a FK real que a E05 deixou pendente ("FK real no E03").
        Schema::table('manifestacao_privacidade', function (Blueprint $table): void {
            $table->foreign('participante_id')->references('id')->on('participante');
        });

        Schema::table('dado_sensivel_participante', function (Blueprint $table): void {
            $table->foreign('participante_id')->references('id')->on('participante');
        });
    }

    public function down(): void
    {
        Schema::table('dado_sensivel_participante', function (Blueprint $table): void {
            $table->dropForeign(['participante_id']);
        });

        Schema::table('manifestacao_privacidade', function (Blueprint $table): void {
            $table->dropForeign(['participante_id']);
        });

        Schema::dropIfExists('excecao_etaria');
        Schema::dropIfExists('solicitacao_vinculo');
        Schema::dropIfExists('responsavel_participante');
        Schema::dropIfExists('participante');
    }
};
