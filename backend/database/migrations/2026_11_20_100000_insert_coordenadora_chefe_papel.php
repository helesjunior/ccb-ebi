<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * O papel `coordenadora_chefe` (E07) chega a producao por migration: o deploy
 * roda apenas `migrate --force` (infra/compose.yml), sem seeder. Idempotente,
 * para conviver com o PapelSeeder nos testes e no ambiente local.
 */
return new class extends Migration
{
    private const CODIGO = 'coordenadora_chefe';

    public function up(): void
    {
        if (! Schema::hasTable('papeis')) {
            return;
        }

        if (DB::table('papeis')->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        DB::table('papeis')->insert([
            'id' => (string) Str::ulid(),
            'codigo' => self::CODIGO,
            'descricao' => 'Coordenadora chefe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Apagar o papel quebraria vinculos existentes; so sai se ninguem o usa.
        $ids = DB::table('papeis')->where('codigo', self::CODIGO)->pluck('id');

        if ($ids->isEmpty() || DB::table('vinculo_acesso')->whereIn('papel_id', $ids)->exists()) {
            return;
        }

        DB::table('papeis')->whereIn('id', $ids)->delete();
    }
};
