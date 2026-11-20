<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma coordenadora chefe vigente por EBI (E07). A vaga e a coluna unica no
 * vinculo ativo: `chefe_de_ebi_id` guarda o EBI e so existe enquanto o vinculo
 * esta vigente (o model preenche e limpa no `saving`). Nao ha backfill: nao ha
 * chefe antes desta etapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculo_acesso', function (Blueprint $table): void {
            $table->string('chefe_de_ebi_id', 26)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('vinculo_acesso', function (Blueprint $table): void {
            $table->dropUnique(['chefe_de_ebi_id']);
            $table->dropColumn('chefe_de_ebi_id');
        });
    }
};
