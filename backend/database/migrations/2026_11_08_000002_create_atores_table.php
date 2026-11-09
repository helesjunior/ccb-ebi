<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('atores');
    }
};
