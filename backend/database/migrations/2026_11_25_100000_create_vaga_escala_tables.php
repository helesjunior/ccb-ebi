<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E07 (evolucao) - Vaga de escala e ofertas: a auxiliar faltou (substituicao)
 * ou a dona quer mais gente (acrescimo); outras equipes se oferecem e a dona
 * escolhe. Ver a decisao 1 da spec.
 *
 * O membro original pode ser apagado na retirada: `substitui_membro_id` cai
 * para nulo e `substituido_usuario_id` (gravado na abertura) preserva quem era.
 * Retirar o membro criado pela oferta nao apaga a oferta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaga_escala', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unique('id', 'vaga_escala_id_unique');
            $table->foreignUlid('escala_id')->constrained('escala')->cascadeOnDelete();
            $table->string('funcao')->default('auxiliar');
            $table->foreignUlid('substitui_membro_id')->nullable()->constrained('escala_membro')->nullOnDelete();
            $table->ulid('substituido_usuario_id')->nullable();
            // Observacao publica: todo mundo do EBI le.
            $table->string('observacao', 280)->nullable();
            $table->string('status')->default('aberta');
            $table->ulid('aberta_por')->nullable();
            $table->ulid('encerrada_por')->nullable();
            $table->dateTimeTz('encerrada_em')->nullable();
            $table->string('motivo_encerramento', 40)->nullable();
            $table->timestamps();

            $table->index(['escala_id', 'status']);
        });

        Schema::create('oferta_vaga_escala', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unique('id', 'oferta_vaga_escala_id_unique');
            $table->foreignUlid('vaga_id')->constrained('vaga_escala')->cascadeOnDelete();
            $table->ulid('usuario_id');
            $table->string('status')->default('pendente');
            $table->foreignUlid('membro_criado_id')->nullable()->constrained('escala_membro')->nullOnDelete();
            $table->timestamps();

            $table->index(['vaga_id', 'status']);
            $table->index(['usuario_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oferta_vaga_escala');
        Schema::dropIfExists('vaga_escala');
    }
};
