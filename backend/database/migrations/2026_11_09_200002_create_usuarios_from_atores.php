<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reconcilia a identidade in-place: `atores` (esqueleto, bigint) vira `usuarios`
 * (entidade real, ULID). Cria a forma final e copia as linhas existentes
 * (nao ha producao; so o ator semeado). A coluna `papel` sai do usuario: os
 * papeis passam a viver em `vinculo_acesso` (migracao seguinte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('nome');
            $table->string('email_normalizado')->unique();
            $table->timestamp('email_verificado_em')->nullable();
            $table->string('senha')->nullable();
            $table->string('identidade_federada')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
        });

        if (Schema::hasTable('atores')) {
            $agora = now();

            foreach (DB::table('atores')->get() as $ator) {
                DB::table('usuarios')->insert([
                    'id' => (string) Str::ulid(),
                    'nome' => $ator->nome,
                    'email_normalizado' => mb_strtolower(trim($ator->email)),
                    'email_verificado_em' => $agora,
                    'senha' => $ator->senha,
                    'identidade_federada' => null,
                    'status' => 'ativo',
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
