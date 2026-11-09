<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_nacional', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('regionais', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('central_nacional_id')->constrained('central_nacional');
            $table->string('nome');
            $table->string('codigo')->unique();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('setores', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('regional_id')->constrained('regionais');
            $table->string('nome');
            $table->string('codigo')->unique();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('igrejas', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('codigo')->unique();
            $table->string('nome');
            $table->string('endereco')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('igreja_vinculacao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas');
            $table->foreignUlid('setor_id')->constrained('setores');
            $table->date('inicio');
            $table->date('fim')->nullable();
            $table->timestamps();

            $table->index(['igreja_id', 'fim']);
        });

        // Reforco no banco: no maximo um vinculo vigente (fim IS NULL) por igreja.
        // Indice unico parcial, suportado por PostgreSQL e SQLite.
        Schema::getConnection()->statement(
            'CREATE UNIQUE INDEX igreja_vinculacao_vigente_unique ON igreja_vinculacao (igreja_id) WHERE fim IS NULL'
        );

        Schema::create('ebis', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('igreja_id')->constrained('igrejas');
            $table->string('nome');
            $table->unsignedSmallInteger('participantes_por_auxiliar')->default(4);
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebis');
        Schema::dropIfExists('igreja_vinculacao');
        Schema::dropIfExists('igrejas');
        Schema::dropIfExists('setores');
        Schema::dropIfExists('regionais');
        Schema::dropIfExists('central_nacional');
    }
};
