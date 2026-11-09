<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checar(fn () => DB::connection()->getPdo()),
            'redis' => $this->checar(fn () => Redis::connection()->ping()),
        ];

        $ok = ! in_array('fail', $checks, true);

        return new JsonResponse([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    /**
     * @param  callable(): mixed  $probe
     */
    private function checar(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }
}
