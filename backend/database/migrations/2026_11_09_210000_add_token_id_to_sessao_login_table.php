<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga a sessao de dispositivo ao token Sanctum correspondente (RF-05): assim
 * revogar um dispositivo realmente invalida a sessao, e a expiracao do token
 * acompanha a do dispositivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessao_login', function (Blueprint $table): void {
            $table->foreignId('token_id')->nullable()->after('usuario_id')
                ->constrained('personal_access_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sessao_login', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('token_id');
        });
    }
};
