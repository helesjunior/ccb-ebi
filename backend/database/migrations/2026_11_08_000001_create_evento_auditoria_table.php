<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_auditoria', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('sequencia')->unique();
            $table->string('hash_anterior', 64)->nullable();
            $table->string('hash_encadeado', 64);
            $table->string('ator_id')->nullable();
            $table->string('acao');
            $table->string('recurso');
            $table->string('escopo')->nullable();
            $table->string('origem');
            $table->string('motivo')->nullable();
            $table->json('metadados_minimizados')->nullable();
            $table->timestamp('ocorrido_em');
            $table->timestamps();

            $table->index('ator_id');
            $table->index('recurso');
            $table->index('ocorrido_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_auditoria');
    }
};
