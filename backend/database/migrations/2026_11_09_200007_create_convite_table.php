<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convites para a equipe (RF-05): o token secreto vai em claro so no e-mail;
 * no banco fica o hash. Uso unico (aceito_em) e cancelavel (cancelado_em).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convite', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email_normalizado');
            $table->foreignUlid('papel_id')->constrained('papeis');
            $table->string('tipo_escopo');
            $table->string('escopo_id');
            $table->string('token_hash');
            $table->timestamp('expira_em');
            $table->foreignUlid('convidante_id')->constrained('usuarios');
            $table->timestamp('aceito_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();

            $table->index(['email_normalizado', 'aceito_em']);
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convite');
    }
};
