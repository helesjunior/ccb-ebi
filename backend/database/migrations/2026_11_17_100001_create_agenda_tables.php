<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_ebi', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ebi_id')->constrained('ebis');
            $table->unsignedTinyInteger('dia_semana');
            $table->string('hora_inicio', 5);
            $table->string('hora_fim', 5);
            $table->date('inicio_vigencia');
            $table->date('fim_vigencia')->nullable();
            $table->timestamps();
            $table->index(['ebi_id', 'dia_semana']);
        });

        Schema::create('agenda_excecao', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('ebi_id')->constrained('ebis');
            $table->foreignUlid('agenda_ebi_id')->nullable()->constrained('agenda_ebi');
            $table->date('data');
            $table->string('tipo');
            $table->string('hora_inicio', 5)->nullable();
            $table->string('hora_fim', 5)->nullable();
            $table->text('motivo');
            $table->timestamps();
            $table->index(['ebi_id', 'data']);
        });

        DB::statement('CREATE UNIQUE INDEX agenda_excecao_dia_unico ON agenda_excecao (ebi_id, data, tipo) WHERE agenda_ebi_id IS NULL');
        DB::statement("CREATE UNIQUE INDEX agenda_excecao_faixa_unico ON agenda_excecao (agenda_ebi_id, data) WHERE tipo = 'horario_especial'");
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_excecao');
        Schema::dropIfExists('agenda_ebi');
    }
};
