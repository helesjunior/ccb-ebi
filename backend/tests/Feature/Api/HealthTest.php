<?php

it('responde ok quando banco e redis respondem', function () {
    $this->getJson('/api/v1/health')->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.redis', 'ok');
});

it('responde 503 quando uma dependencia cai', function () {
    // Simula queda do Redis trocando o host para um inválido em runtime.
    config(['database.redis.default.host' => '127.0.0.1', 'database.redis.default.port' => 1]);
    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.redis', 'fail');
});
