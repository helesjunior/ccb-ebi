<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Limpa o cache antes de cada teste para isolar o rate limiter
// (o store em memória persiste entre testes no mesmo processo).
beforeEach(function () {
    Cache::clear();
})->in('Feature');
