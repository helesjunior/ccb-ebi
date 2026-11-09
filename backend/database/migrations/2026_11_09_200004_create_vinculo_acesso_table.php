<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vinculo de acesso: liga um usuario a um papel dentro de um escopo territorial,
 * com vigencia [inicio, fim) e `fim IS NULL` = ativo (mesma forma da
 * `igreja_vinculacao`). O escopo_id e opaco (cruza modulos): referencia um no
 * da Organizacao sem FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculo_acesso', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('usuario_id')->constrained('usuarios');
            $table->foreignUlid('papel_id')->constrained('papeis');
            $table->string('tipo_escopo');
            $table->string('escopo_id');
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();

            $table->index(['usuario_id', 'fim']);
        });

        // No maximo um vinculo vigente por (usuario, papel, tipo, escopo).
        Schema::getConnection()->statement(
            'CREATE UNIQUE INDEX vinculo_acesso_vigente_unique ON vinculo_acesso (usuario_id, papel_id, tipo_escopo, escopo_id) WHERE fim IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculo_acesso');
    }
};
