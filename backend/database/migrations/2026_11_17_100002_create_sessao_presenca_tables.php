<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ebi_id')->constrained('ebis');
            $table->foreignUlid('igreja_id')->constrained('igrejas');
            $table->foreignUlid('agenda_ebi_id')->nullable()->constrained('agenda_ebi');
            $table->timestampTz('inicio_previsto');
            $table->timestampTz('fim_previsto');
            $table->timestampTz('inicio_real')->nullable();
            $table->timestampTz('fim_real')->nullable();
            $table->string('tema')->nullable();
            $table->string('passagem_biblica')->nullable();
            $table->string('status')->default('prevista');
            $table->boolean('realizada')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->foreignUlid('cancelada_por')->nullable()->constrained('usuarios');
            $table->json('auxiliares_escalados')->nullable();
            $table->timestamps();
            $table->index(['ebi_id', 'inicio_previsto']);
        });

        Schema::create('sessao_correcao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sessao_id')->constrained('sessao');
            $table->foreignUlid('autor_id')->constrained('usuarios');
            $table->text('motivo');
            $table->json('antes');
            $table->json('depois');
            $table->timestampTz('ocorrido_em');
            $table->timestamps();
        });

        Schema::create('presenca', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('sessao_id')->constrained('sessao');
            $table->foreignUlid('participante_id')->constrained('participante');
            $table->foreignUlid('registrado_por')->constrained('usuarios');
            $table->timestampTz('registrado_em');
            $table->string('origem');
            $table->string('status')->default('valida');
            $table->foreignUlid('estornado_por')->nullable()->constrained('usuarios');
            $table->timestampTz('estornado_em')->nullable();
            $table->text('motivo_estorno')->nullable();
            $table->timestamps();
            $table->index(['sessao_id', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX sessao_ebi_inicio_unico ON sessao (ebi_id, inicio_previsto) WHERE status <> 'cancelada'");
        DB::statement("CREATE UNIQUE INDEX presenca_valida_unica ON presenca (sessao_id, participante_id) WHERE status = 'valida'");
    }

    public function down(): void
    {
        Schema::dropIfExists('presenca');
        Schema::dropIfExists('sessao_correcao');
        Schema::dropIfExists('sessao');
    }
};
