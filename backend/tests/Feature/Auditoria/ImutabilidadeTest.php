<?php

use App\Domain\Auditoria\Models\EventoAuditoria;

it('proibe update na auditoria', function () {
    $e = EventoAuditoria::factory()->create(['acao' => 'probe']);
    expect(fn () => $e->update(['acao' => 'outra']))
        ->toThrow(RuntimeException::class);
});

it('proibe delete na auditoria', function () {
    $e = EventoAuditoria::factory()->create();
    expect(fn () => $e->delete())->toThrow(RuntimeException::class);
});
