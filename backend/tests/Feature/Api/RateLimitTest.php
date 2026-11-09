<?php

it('responde 429 ao exceder o limite', function () {
    for ($i = 0; $i < 61; $i++) {
        $r = $this->getJson('/api/v1/health');
    }
    $r->assertStatus(429)->assertHeader('Retry-After');
});
