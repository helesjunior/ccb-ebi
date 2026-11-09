<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fatores de MFA (CA-15): TOTP (segredo cifrado) e e-mail de contingencia.
 * No maximo um fator confirmado por (usuario, tipo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fator_mfa', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->constrained('usuarios');
            $table->string('tipo');
            $table->text('segredo_cifrado')->nullable();
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'tipo']);
        });

        Schema::getConnection()->statement(
            'CREATE UNIQUE INDEX fator_mfa_confirmado_unique ON fator_mfa (usuario_id, tipo) WHERE confirmado_em IS NOT NULL'
        );

        Schema::create('codigo_recuperacao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->constrained('usuarios');
            $table->string('hash');
            $table->timestamp('usado_em')->nullable();
            $table->timestamps();

            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigo_recuperacao');
        Schema::dropIfExists('fator_mfa');
    }
};
