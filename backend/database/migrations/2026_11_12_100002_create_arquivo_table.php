<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arquivos do sistema (E08, reutilizavel pela Etapa 5). Todo upload passa pelo
 * pipeline: quarentena -> validacao de tipo real -> antivirus -> bucket
 * cifrado. `status_antivirus` registra o veredito; arquivos infectados/invalidos
 * sao apagados (nao ficam no bucket).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arquivo', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('finalidade');
            $table->string('nome_original');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('chave_storage');
            $table->string('bucket');
            $table->boolean('cifrado')->default(false);
            $table->string('status_antivirus')->default('pendente'); // pendente|limpo|infectado|invalido
            $table->string('enviado_por')->nullable();
            $table->timestamps();

            $table->index('finalidade');
            $table->index('status_antivirus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arquivo');
    }
};
