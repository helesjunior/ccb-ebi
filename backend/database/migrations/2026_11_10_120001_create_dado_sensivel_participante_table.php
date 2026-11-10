<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dado sensível do participante (E05/A2): conteudo_cifrado (chave própria) OU
 * referencia_segura (bucket próprio, E08). participante_id sem FK real até o E03
 * (mesma decisão de manifestacao_privacidade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dado_sensivel_participante', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('participante_id')->nullable();  // FK real no E03
            $table->string('tipo');
            $table->text('conteudo_cifrado')->nullable();
            $table->string('referencia_segura')->nullable();
            $table->string('finalidade');
            $table->string('base_legal');
            $table->string('controle_acesso');
            $table->date('prazo_retencao')->nullable();
            $table->timestamp('expurgado_em')->nullable();
            $table->timestamps();

            $table->index(['participante_id', 'tipo']);
            $table->index('prazo_retencao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dado_sensivel_participante');
    }
};
