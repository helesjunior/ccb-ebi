<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessoes por dispositivo (RF-05): cada login registra um dispositivo com
 * expiracao por perfil e permite revogacao individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessao_login', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->constrained('usuarios');
            $table->string('dispositivo');
            $table->timestamp('criada_em');
            $table->timestamp('expira_em');
            $table->timestamp('revogada_em')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'revogada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessao_login');
    }
};
