<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Services\RegistradorAuditoria;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RegistrarEvento::class, RegistradorAuditoria::class);
    }
}
