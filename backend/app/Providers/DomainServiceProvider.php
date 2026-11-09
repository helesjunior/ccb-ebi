<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Services\RegistradorAuditoria;
use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Domain\Identidade\Services\AutenticadorFicticio;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Services\ConsultaOrganizacao;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RegistrarEvento::class, RegistradorAuditoria::class);
        $this->app->bind(AutenticarAtor::class, AutenticadorFicticio::class);
        $this->app->bind(ConsultarOrganizacao::class, ConsultaOrganizacao::class);
    }
}
