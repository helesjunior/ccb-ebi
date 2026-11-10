<?php

namespace App\Providers;

use App\Domain\Auditoria\Contracts\ConsultarAuditoria;
use App\Domain\Auditoria\Contracts\RegistrarEvento;
use App\Domain\Auditoria\Services\ConsultaAuditoria;
use App\Domain\Auditoria\Services\RegistradorAuditoria;
use App\Domain\DadosSensiveis\Contracts\ArmazenarDadoSensivel;
use App\Domain\DadosSensiveis\Services\ArmazenadorDadoSensivel;
use App\Domain\Identidade\Contracts\AceitarConvite;
use App\Domain\Identidade\Contracts\Autenticar;
use App\Domain\Identidade\Contracts\AutorizarOperacao;
use App\Domain\Identidade\Contracts\ConsultarUsuarios;
use App\Domain\Identidade\Contracts\ConsultarVinculos;
use App\Domain\Identidade\Contracts\ExigirMfa;
use App\Domain\Identidade\Contracts\GerenciarMfa;
use App\Domain\Identidade\Contracts\GerenciarSessao;
use App\Domain\Identidade\Contracts\GerirConvite;
use App\Domain\Identidade\Contracts\GerirUsuario;
use App\Domain\Identidade\Contracts\GerirVinculo;
use App\Domain\Identidade\Contracts\RecuperarSenha;
use App\Domain\Identidade\Contracts\RegistrarConta;
use App\Domain\Identidade\Contracts\ResolverContextoAtuacao;
use App\Domain\Identidade\Contracts\ResolverEscopo;
use App\Domain\Identidade\Contracts\VerificarEmail;
use App\Domain\Identidade\Services\AceitadorConvite;
use App\Domain\Identidade\Services\Autenticador;
use App\Domain\Identidade\Services\AutorizadorOperacao;
use App\Domain\Identidade\Services\ConsultaUsuarios;
use App\Domain\Identidade\Services\ConsultaVinculos;
use App\Domain\Identidade\Services\GerenciadorConta;
use App\Domain\Identidade\Services\GerenciadorConvite;
use App\Domain\Identidade\Services\GerenciadorMfa;
use App\Domain\Identidade\Services\GerenciadorSessao;
use App\Domain\Identidade\Services\GerenciadorUsuario;
use App\Domain\Identidade\Services\GerenciadorVinculo;
use App\Domain\Identidade\Services\ResolvedorContextoAtuacao;
use App\Domain\Identidade\Services\ResolvedorEscopo;
use App\Domain\Identidade\Services\ServicoRecuperacaoSenha;
use App\Domain\Organizacao\Contracts\ConsultarHierarquia;
use App\Domain\Organizacao\Contracts\ConsultarOrganizacao;
use App\Domain\Organizacao\Contracts\GerirOrganizacao;
use App\Domain\Organizacao\Contracts\TransferirIgreja;
use App\Domain\Organizacao\Services\ConsultaHierarquia;
use App\Domain\Organizacao\Services\ConsultaOrganizacao;
use App\Domain\Organizacao\Services\GerenciadorOrganizacao;
use App\Domain\Organizacao\Services\ServicoTransferencia;
use App\Domain\Privacidade\Contracts\ConsultarConsentimentos;
use App\Domain\Privacidade\Contracts\GerirTermos;
use App\Domain\Privacidade\Contracts\RegistrarConsentimento;
use App\Domain\Privacidade\Contracts\SolicitarDireitoTitular;
use App\Domain\Privacidade\Services\GerenciadorConsentimento;
use App\Domain\Privacidade\Services\GerenciadorSolicitacao;
use App\Domain\Privacidade\Services\GerenciadorTermos;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RegistrarEvento::class, RegistradorAuditoria::class);
        $this->app->bind(ConsultarAuditoria::class, ConsultaAuditoria::class);
        $this->app->bind(Autenticar::class, Autenticador::class);
        $this->app->bind(GerenciarMfa::class, GerenciadorMfa::class);
        $this->app->bind(ExigirMfa::class, GerenciadorMfa::class);
        $this->app->bind(GerenciarSessao::class, GerenciadorSessao::class);
        $this->app->bind(RecuperarSenha::class, ServicoRecuperacaoSenha::class);
        $this->app->bind(ResolverEscopo::class, ResolvedorEscopo::class);
        $this->app->bind(AutorizarOperacao::class, AutorizadorOperacao::class);
        $this->app->bind(ConsultarUsuarios::class, ConsultaUsuarios::class);
        $this->app->bind(ConsultarVinculos::class, ConsultaVinculos::class);
        $this->app->bind(GerirUsuario::class, GerenciadorUsuario::class);
        $this->app->bind(GerirConvite::class, GerenciadorConvite::class);
        $this->app->bind(AceitarConvite::class, AceitadorConvite::class);
        $this->app->bind(GerirVinculo::class, GerenciadorVinculo::class);
        $this->app->bind(ResolverContextoAtuacao::class, ResolvedorContextoAtuacao::class);
        $this->app->bind(RegistrarConta::class, GerenciadorConta::class);
        $this->app->bind(VerificarEmail::class, GerenciadorConta::class);
        $this->app->bind(ConsultarHierarquia::class, ConsultaHierarquia::class);
        $this->app->bind(ConsultarOrganizacao::class, ConsultaOrganizacao::class);
        $this->app->bind(TransferirIgreja::class, ServicoTransferencia::class);
        $this->app->bind(GerirOrganizacao::class, GerenciadorOrganizacao::class);
        $this->app->bind(GerirTermos::class, GerenciadorTermos::class);
        $this->app->bind(RegistrarConsentimento::class, GerenciadorConsentimento::class);
        $this->app->bind(ConsultarConsentimentos::class, GerenciadorConsentimento::class);
        $this->app->bind(SolicitarDireitoTitular::class, GerenciadorSolicitacao::class);
        $this->app->bind(ArmazenarDadoSensivel::class, ArmazenadorDadoSensivel::class);
    }
}
