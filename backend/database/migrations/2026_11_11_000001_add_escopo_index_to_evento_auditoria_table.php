<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indice em `evento_auditoria.escopo`: `ConsultaAuditoria` filtra por escopo
 * (`whereNotNull('escopo')->distinct()` e `whereIn('escopo', ...)`) e ordena por
 * `id` (cursor). O composto `(escopo, id)` cobre o `whereIn` ja ordenado,
 * evitando sort em disco na consulta da trilha. O rework da query fica em A2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_auditoria', function (Blueprint $table): void {
            $table->index(['escopo', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('evento_auditoria', function (Blueprint $table): void {
            $table->dropIndex(['escopo', 'id']);
        });
    }
};
