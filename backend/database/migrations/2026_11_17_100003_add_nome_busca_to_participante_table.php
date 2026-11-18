<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participante', function (Blueprint $table): void {
            $table->string('nome_busca', 255)->nullable()->after('nome_social')->index();
        });

        DB::table('participante')->select(['id', 'nome'])->orderBy('id')->chunkById(500, function ($linhas): void {
            foreach ($linhas as $linha) {
                DB::table('participante')->where('id', $linha->id)->update([
                    'nome_busca' => mb_strtolower(Str::ascii((string) $linha->nome)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('participante', function (Blueprint $table): void {
            $table->dropIndex(['nome_busca']);
            $table->dropColumn('nome_busca');
        });
    }
};
