<?php

namespace App\Providers;

use App\Domain\Identidade\Models\Ator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Autorizacao provisoria (RBAC real na Etapa 3): o papel administrativo
        // ficticio 'coordenadora' pode gerir a organizacao.
        Gate::define('gerir-organizacao', fn (?Ator $ator) => $ator?->papel === 'coordenadora');
    }
}
