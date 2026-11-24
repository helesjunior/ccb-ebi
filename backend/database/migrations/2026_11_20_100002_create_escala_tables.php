<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E07 - Escalas (RF-07): uma escala por sessao, seus membros por funcao e os
 * pedidos de troca de um membro.
 *
 * A unicidade de membro ativo por escala e por horario e do servico, nao do
 * banco: em SQLite nao ha indice parcial e a regra depende da vigencia do
 * usuario no momento (Task 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escala', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sessao_id')->unique()->constrained('sessao');
            $table->ulid('ebi_id')->index();
            $table->ulid('igreja_id')->index();
            $table->unsignedSmallInteger('estimativa_participantes');
            $table->unsignedSmallInteger('participantes_por_auxiliar');
            $table->string('status')->default('prevista');
            $table->ulid('aprovado_por')->nullable();
            $table->dateTimeTz('aprovado_em')->nullable();
            $table->ulid('criado_por');
            $table->timestamps();
        });

        Schema::create('escala_membro', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // O auto-relacionamento do substituto precisa de um indice unico
            // visivel: o PostgreSQL nao aceita FK para a chave primaria criada
            // pelo `ulid()->primary()` na mesma migration sem o indice.
            $table->unique('id', 'escala_membro_id_unique');
            $table->foreignUlid('escala_id')->constrained('escala')->cascadeOnDelete();
            $table->ulid('usuario_id')->index();
            $table->string('funcao');
            $table->string('status')->default('escalado');
            $table->dateTimeTz('confirmado_em')->nullable();
            $table->boolean('presente')->nullable();
            $table->ulid('presenca_registrada_por')->nullable();
            $table->dateTimeTz('presenca_registrada_em')->nullable();
            $table->foreignUlid('substitui_membro_id')->nullable()->constrained('escala_membro')->nullOnDelete();
            $table->timestamps();

            $table->index(['escala_id', 'usuario_id']);
        });

        Schema::create('solicitacao_troca_escala', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('escala_membro_id')->constrained('escala_membro')->cascadeOnDelete();
            $table->ulid('solicitante_id');
            $table->string('motivo', 500)->nullable();
            $table->string('status')->default('pendente');
            $table->foreignUlid('substituto_membro_id')->nullable()->constrained('escala_membro')->nullOnDelete();
            $table->ulid('decidido_por')->nullable();
            $table->dateTimeTz('decidido_em')->nullable();
            $table->string('motivo_decisao', 500)->nullable();
            $table->timestamps();

            $table->index(['escala_membro_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_troca_escala');
        Schema::dropIfExists('escala_membro');
        Schema::dropIfExists('escala');
    }
};
