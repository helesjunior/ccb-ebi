<?php

namespace App\Providers;

use App\Domain\Identidade\Support\Operacoes;
use App\Policies\AccessGrantPolicy;
use App\Policies\AuditoriaPolicy;
use App\Policies\ConsentimentoPolicy;
use App\Policies\ConvitePolicy;
use App\Policies\OrganizacaoPolicy;
use App\Policies\ParticipantePolicy;
use App\Policies\UsuarioPolicy;
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

        // Autorizacao por operacao (CA-03): as abilities de Gate delegam a
        // Policy, que consulta o nucleo AutorizarOperacao com o ContextoAtuacao.
        Gate::define(Operacoes::ORGANIZACAO_LER, [OrganizacaoPolicy::class, 'ler']);
        Gate::define(Operacoes::ORGANIZACAO_GERENCIAR, [OrganizacaoPolicy::class, 'gerenciar']);
        Gate::define(Operacoes::USUARIO_LER, [UsuarioPolicy::class, 'ler']);
        Gate::define(Operacoes::USUARIO_GERENCIAR, [UsuarioPolicy::class, 'gerenciar']);
        Gate::define(Operacoes::CONVITE_GERENCIAR, [ConvitePolicy::class, 'gerenciar']);
        Gate::define(Operacoes::ACESSO_GERENCIAR, [AccessGrantPolicy::class, 'gerenciar']);
        Gate::define(Operacoes::CONSENTIMENTO_LER, [ConsentimentoPolicy::class, 'ler']);
        Gate::define(Operacoes::AUDITORIA_LER, [AuditoriaPolicy::class, 'ler']);
        Gate::define(Operacoes::PARTICIPANTE_LER, [ParticipantePolicy::class, 'ler']);
        Gate::define(Operacoes::PARTICIPANTE_CADASTRAR, [ParticipantePolicy::class, 'cadastrar']);
        Gate::define(Operacoes::PARTICIPANTE_CORRIGIR, [ParticipantePolicy::class, 'corrigir']);
        Gate::define(Operacoes::PARTICIPANTE_MESCLAR, [ParticipantePolicy::class, 'mesclar']);
    }
}
