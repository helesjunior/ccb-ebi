<?php

use App\Domain\Identidade\Models\Usuario;
use Illuminate\Support\Facades\Schema;

it('cria a tabela usuarios com a forma final e sem papel', function () {
    expect(Schema::hasTable('usuarios'))->toBeTrue()
        ->and(Schema::hasTable('atores'))->toBeFalse()
        ->and(Schema::hasColumn('usuarios', 'email_normalizado'))->toBeTrue()
        ->and(Schema::hasColumn('usuarios', 'senha'))->toBeTrue()
        ->and(Schema::hasColumn('usuarios', 'papel'))->toBeFalse();
});

it('persiste um usuario com id ULID', function () {
    $u = Usuario::factory()->create(['nome' => 'Maria']);
    expect($u->id)->toBeString()->and(strlen($u->id))->toBe(26);
});
