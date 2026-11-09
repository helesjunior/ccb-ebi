<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Services\RegistradorAuditoria;
use App\Domain\Identidade\Contracts\AutenticarAtor;
use App\Domain\Identidade\Contracts\ResolverContextoAtuacao;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Services\AutenticadorFicticio;
use App\Domain\Identidade\Services\ResolvedorContextoAtuacao;
use App\Domain\Identidade\Services\ResolvedorEscopo;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Services\ConsultaHierarquia;
use App\Domain\Organizacao\Services\ConsultaOrganizacao;
use App\Domain\Organizacao\Services\GerenciadorOrganizacao;
use App\Domain\Organizacao\Services\ServicoTransferencia;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RegistrarEvento::class, RegistradorAuditoria::class);
        $this->app->bind(AutenticarAtor::class, AutenticadorFicticio::class);
        $this->app->bind(ResolverEscopo::class, ResolvedorEscopo::class);
        $this->app->bind(ResolverContextoAtuacao::class, ResolvedorContextoAtuacao::class);
        $this->app->bind(ConsultarHierarquia::class, ConsultaHierarquia::class);
        $this->app->bind(ConsultarOrganizacao::class, ConsultaOrganizacao::class);
        $this->app->bind(TransferirIgreja::class, ServicoTransferencia::class);
        $this->app->bind(GerirOrganizacao::class, GerenciadorOrganizacao::class);
    }
}
