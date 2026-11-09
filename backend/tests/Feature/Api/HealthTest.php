<?php

use Illuminate\Support\Facades\Redis;

it('responde 503 quando uma dependencia cai', function () {
    // Simula queda do Redis: a conexão lança ao tentar o ping.
    Redis::shouldReceive('connection')
        ->andThrow(new RuntimeException('Redis indisponível'));

    $this->getJson('/api/v1/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.redis', 'fail');
});
