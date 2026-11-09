<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recolhe o esqueleto de identidade: remove `atores` e a tabela `users` padrao
 * do Laravel (junto com o `App\Models\User` orfao). A identidade real vive em
 * `usuarios` (ULID).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('atores');
        Schema::dropIfExists('users');
    }

    public function down(): void
    {
        Schema::create('atores', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->string('papel');
            $table->string('senha');
            $table->timestamps();
        });
    }
};
