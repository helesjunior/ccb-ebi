<?php

use App\Domain\Identidade\Services\DesafioMfa;
use App\Http\Controllers\Api\V1\AccessGrantController;
use App\Http\Controllers\Api\V1\AgeExceptionController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AgendaExcecaoController;
use App\Http\Controllers\Api\V1\ArquivoController;
use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CentralController;
use App\Http\Controllers\Api\V1\ChurchController;
use App\Http\Controllers\Api\V1\EbiController;
use App\Http\Controllers\Api\V1\EquipeEbiController;
use App\Http\Controllers\Api\V1\EscalaController;
use App\Http\Controllers\Api\V1\EscalaMembroController;
use App\Http\Controllers\Api\V1\GuardianController;
use App\Http\Controllers\Api\V1\GuardianRequestController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HistoricoPresencaController;
use App\Http\Controllers\Api\V1\InvitationAcceptController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MfaController;
use App\Http\Controllers\Api\V1\MinhasEscalasController;
use App\Http\Controllers\Api\V1\ParticipantController;
use App\Http\Controllers\Api\V1\ParticipantMergeController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\PresencaController;
use App\Http\Controllers\Api\V1\PrivacyConsentController;
use App\Http\Controllers\Api\V1\PrivacyRequestController;
use App\Http\Controllers\Api\V1\PrivacyTermController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\RegisterController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SectorController;
use App\Http\Controllers\Api\V1\SessaoController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\TrocaEscalaController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\IdempotenciaMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('health', [HealthController::class, 'health'])->name('health');

    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');

    // Identidade - rotas publicas (sem sessao)
    Route::post('auth/register-guardian', [RegisterController::class, 'register'])->name('auth.register-guardian');
    Route::post('auth/verify-email', [RegisterController::class, 'verify'])->name('auth.verify-email');
    Route::post('auth/email/resend', [RegisterController::class, 'resend'])->name('auth.email.resend');

    // Recuperacao e redefinicao de senha (RF-03), sem enumeracao de contas.
    Route::post('auth/password/forgot', [PasswordController::class, 'forgot'])->name('auth.password.forgot');
    Route::post('auth/password/reset', [PasswordController::class, 'reset'])->name('auth.password.reset');

    // Aceite de convite: rota publica; o token secreto do convite e a credencial.
    Route::post('invitations/{id}/accept', [InvitationAcceptController::class, 'accept'])->name('invitations.accept');

    // Segundo fator do login: autenticado pelo token de desafio, escopo restrito.
    Route::middleware(['auth:sanctum', 'abilities:'.DesafioMfa::ABILITY])
        ->post('auth/mfa/verify', [MfaController::class, 'verify'])
        ->name('auth.mfa.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'me'])->name('me');
        Route::patch('me', [MeController::class, 'atualizarPerfil'])->name('me.update');
        Route::post('me/password', [MeController::class, 'alterarSenha'])->name('me.password');
        Route::get('me/scopes', [MeController::class, 'scopes'])->name('me.scopes');

        // Seletor de EBIs do ator (RF-06/E04): sem Gate — a lista e sempre o
        // recorte dos proprios vinculos (ou do contexto de atuacao presente).
        Route::get('me/ebis', [MeController::class, 'ebis'])->name('me.ebis');

        // Termos de privacidade (RF-04): qualquer usuario autenticado le a
        // versao vigente que precisa aceitar.
        Route::get('privacy/terms', [PrivacyTermController::class, 'index'])->name('privacy.terms');

        // Consentimentos (RF-04): o titular registra e consulta os proprios.
        Route::get('privacy/consents', [PrivacyConsentController::class, 'index'])->name('privacy.consents.index');
        Route::post('privacy/consents', [PrivacyConsentController::class, 'store'])->name('privacy.consents.store');

        // Leitura por operador: o Gate cobre `consentimento.ler`; a subarvore do
        // titular e conferida no controller.
        Route::get('privacy/consents/{id}', [PrivacyConsentController::class, 'show'])
            ->middleware('can:consentimento.ler')->name('privacy.consents.show');

        // Solicitacao de direitos do titular (RF-16): o titular abre um pedido
        // em nome proprio; sem Gate, a rota inteira e do proprio usuario.
        Route::post('privacy/requests', [PrivacyRequestController::class, 'store'])
            ->name('privacy.requests.store');

        // Auditoria (RF-16): leitura sob a operacao `auditoria.ler`; o recorte
        // territorial e aplicado na consulta, que revalida o escopo de cada evento.
        Route::get('audit-events', [AuditEventController::class, 'index'])
            ->middleware('can:auditoria.ler')->name('audit-events.index');

        // MFA do usuario autenticado (inscricao/confirmacao/recuperacao).
        Route::post('me/mfa/totp', [MfaController::class, 'iniciarTotp'])->name('me.mfa.totp');
        Route::post('me/mfa/totp/confirm', [MfaController::class, 'confirmarTotp'])->name('me.mfa.totp.confirm');
        Route::post('me/mfa/recovery-codes', [MfaController::class, 'gerarRecuperacao'])->name('me.mfa.recovery-codes');

        // Sessoes por dispositivo (RF-05).
        Route::get('me/sessions', [SessionController::class, 'index'])->name('me.sessions.index');
        Route::delete('me/sessions/{id}', [SessionController::class, 'destroy'])->name('me.sessions.destroy');

        // Equipe do EBI (RF-05): listagem/gestao de usuarios (matriz).
        Route::get('users', [UserController::class, 'index'])
            ->middleware('can:usuario.ler')->name('users.index');
        Route::get('users/{id}', [UserController::class, 'show'])
            ->middleware('can:usuario.ler')->name('users.show');
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::patch('users/{id}', [UserController::class, 'update'])
                ->middleware('can:usuario.gerenciar')->name('users.update');
        });

        // Convites para a equipe (RF-05).
        Route::middleware('can:convite.gerenciar')->group(function (): void {
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{id}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
        });

        // Concessoes de acesso (nomeacao/afastamento) — RF-05, RN-10.
        Route::middleware('can:acesso.gerenciar')->group(function (): void {
            Route::post('access-grants', [AccessGrantController::class, 'store'])
                ->middleware(IdempotenciaMiddleware::class)->name('access-grants.store');
            Route::delete('access-grants/{id}', [AccessGrantController::class, 'destroy'])->name('access-grants.destroy');
        });

        // Organizacao - leitura (autorizacao por operacao; matriz).
        Route::middleware('can:organizacao.ler')->group(function (): void {
            Route::get('churches', [ChurchController::class, 'index'])->name('churches.index');
            Route::get('churches/{id}', [ChurchController::class, 'show'])->name('churches.show');
            Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
            Route::get('regions/{id}/sectors', [RegionController::class, 'sectors'])->name('regions.sectors');
            Route::get('sectors', [SectorController::class, 'index'])->name('sectors.index');
            Route::get('ebis', [EbiController::class, 'index'])->name('ebis.index');
            Route::get('central', [CentralController::class, 'show'])->name('central.show');
        });

        // Organizacao - escrita (autorizacao por operacao + escopo territorial).
        // Escritas de criacao/transferencia sob idempotencia (Idempotency-Key).
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('churches', [ChurchController::class, 'store'])
                ->middleware('can:organizacao.gerenciar')->name('churches.store');
            Route::post('churches/{id}/transfers', [ChurchController::class, 'transfer'])
                ->name('churches.transfer');
            Route::post('regions', [RegionController::class, 'store'])
                ->middleware('can:organizacao.gerenciar')->name('regions.store');
            Route::post('sectors', [SectorController::class, 'store'])
                ->middleware('can:organizacao.gerenciar')->name('sectors.store');
            Route::post('ebis', [EbiController::class, 'store'])
                ->middleware('can:organizacao.gerenciar')->name('ebis.store');
        });

        Route::patch('churches/{id}', [ChurchController::class, 'update'])
            ->name('churches.update');
        Route::patch('regions/{id}', [RegionController::class, 'update'])
            ->name('regions.update');
        Route::patch('sectors/{id}', [SectorController::class, 'update'])
            ->name('sectors.update');
        Route::patch('ebis/{id}', [EbiController::class, 'update'])
            ->name('ebis.update');

        // Participantes (RF-02, RF-03). Cadastro pelo responsavel sob
        // idempotencia; leitura por operador sob `participante.ler` (matriz).
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('participants', [ParticipantController::class, 'store'])
                ->middleware('can:participante.cadastrar')->name('participants.store');
            Route::post('participants/assisted', [ParticipantController::class, 'storeAssisted'])
                ->middleware('can:participante.cadastrar')->name('participants.assisted');
        });
        Route::get('participants/{id}', [ParticipantController::class, 'show'])
            ->middleware('can:participante.ler')->name('participants.show');

        // Busca/dedup e mesclagem (CA-27). Busca e leitura; mesclagem e escrita
        // sob `participante.mesclar`, idempotente e reversivel.
        Route::get('participants', [ParticipantController::class, 'index'])
            ->middleware('can:participante.ler')->name('participants.index');
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('participants/{id}/merge', [ParticipantMergeController::class, 'store'])
                ->middleware('can:participante.mesclar')->name('participants.merge');
            Route::post('participants/{id}/merge/undo', [ParticipantMergeController::class, 'undo'])
                ->middleware('can:participante.mesclar')->name('participants.merge.undo');
        });

        // Vinculos do responsavel (RF-03). A solicitacao parte do responsavel
        // autenticado; a decisao e da coordenadora (`vinculo.gerenciar`).
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('participants/{participantId}/guardians', [GuardianController::class, 'store'])
                ->name('guardians.store');
        });
        Route::get('me/participants', [GuardianController::class, 'meusDependentes'])
            ->name('me.participants.index');
        Route::get('guardian-requests', [GuardianController::class, 'pedidos'])
            ->name('guardian-requests.index');
        Route::patch('guardian-requests/{id}', [GuardianRequestController::class, 'update'])
            ->middleware('can:vinculo.gerenciar')->name('guardian-requests.update');

        // Excecao inclusiva (FL-06): solicitacao pelo responsavel; decisao pela
        // coordenadora (`excecao.decidir`).
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('participants/{participantId}/age-exceptions', [AgeExceptionController::class, 'store'])
                ->name('age-exceptions.store');
        });
        Route::patch('age-exceptions/{id}', [AgeExceptionController::class, 'update'])
            ->middleware('can:excecao.decidir')->name('age-exceptions.update');

        // Equipe do EBI para montar a escala (E07): quem pode ser escalado.
        Route::get('ebis/{id}/team', [EquipeEbiController::class, 'index'])
            ->name('ebis.team');

        // Escalas (E07, RF-07): a autorizacao e feita no controller
        // (`AutorizacaoEscala`), que distingue 403 de 404 por territorio.
        Route::get('ebis/{id}/schedules', [EscalaController::class, 'index'])
            ->name('schedules.index');
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('sessions/{id}/schedules', [EscalaController::class, 'store'])
                ->name('schedules.store');
            Route::patch('schedules/{id}', [EscalaController::class, 'update'])
                ->name('schedules.update');
            Route::post('schedules/{id}/members', [EscalaMembroController::class, 'store'])
                ->name('schedules.members.store');
            Route::patch('schedule-members/{id}', [EscalaMembroController::class, 'update'])
                ->name('schedule-members.update');
            Route::delete('schedule-members/{id}', [EscalaMembroController::class, 'destroy'])
                ->name('schedule-members.destroy');
            Route::post('schedule-members/{id}/confirm', [EscalaMembroController::class, 'confirmar'])
                ->name('schedule-members.confirm');
            Route::post('schedules/{id}/approve', [EscalaController::class, 'aprovar'])
                ->name('schedules.approve');
            Route::put('schedules/{id}/team-attendance', [EscalaController::class, 'presenca'])
                ->name('schedules.team-attendance');
        });
        Route::get('schedules/{id}', [EscalaController::class, 'show'])->name('schedules.show');

        // "Minhas escalas" (E07): as vagas da propria pessoa no periodo.
        Route::get('me/schedules', [MinhasEscalasController::class, 'index'])->name('me.schedules');

        // Trocas de vaga (E07, RF-07): o membro pede e cancela; a lista e a
        // decisao passam por `troca.decidir` (matriz + territorio).
        Route::get('schedule-swaps', [TrocaEscalaController::class, 'index'])->name('schedule-swaps.index');
        Route::middleware(IdempotenciaMiddleware::class)->group(function (): void {
            Route::post('schedule-swaps', [TrocaEscalaController::class, 'store'])
                ->name('schedule-swaps.store');
            Route::post('schedule-swaps/{id}/cancel', [TrocaEscalaController::class, 'cancel'])
                ->name('schedule-swaps.cancel');
            Route::patch('schedule-swaps/{id}', [TrocaEscalaController::class, 'update'])
                ->name('schedule-swaps.update');
        });

        Route::get('ebis/{id}/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('ebis/{id}/agenda', [AgendaController::class, 'store'])
            ->middleware(IdempotenciaMiddleware::class)->name('agenda.store');
        Route::patch('agenda/{id}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::delete('agenda/{id}', [AgendaController::class, 'destroy'])->name('agenda.destroy');

        Route::get('ebis/{id}/agenda-exceptions', [AgendaExcecaoController::class, 'index'])->name('agenda-exceptions.index');
        Route::post('ebis/{id}/agenda-exceptions', [AgendaExcecaoController::class, 'store'])
            ->middleware(IdempotenciaMiddleware::class)->name('agenda-exceptions.store');
        Route::delete('agenda-exceptions/{id}', [AgendaExcecaoController::class, 'destroy'])->name('agenda-exceptions.destroy');

        Route::post('ebis/{id}/sessions/generate', [SessaoController::class, 'gerar'])
            ->middleware(IdempotenciaMiddleware::class)
            ->name('sessions.generate');

        Route::get('ebis/{id}/sessions', [SessaoController::class, 'index'])->name('sessions.index');
        Route::get('sessions/{id}', [SessaoController::class, 'show'])->name('sessions.show');
        Route::get('sessions/{id}/participants', [PresencaController::class, 'participantes'])
            ->name('sessions.participants');
        Route::get('sessions/{id}/attendance', [PresencaController::class, 'index'])
            ->name('sessions.attendance.index');
        Route::post('sessions/{id}/attendance', [PresencaController::class, 'store'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.attendance.store');
        Route::post('sessions/{id}/contingency-attendance', [PresencaController::class, 'contingencia'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.attendance.contingency');
        Route::post('attendance/{id}/reversal', [PresencaController::class, 'estornar'])
            ->middleware(IdempotenciaMiddleware::class)->name('attendance.reversal');
        Route::get('participants/{id}/attendance', [HistoricoPresencaController::class, 'operador'])
            ->name('participants.attendance');
        Route::get('me/participants/{id}/attendance', [HistoricoPresencaController::class, 'responsavel'])
            ->name('me.participants.attendance');
        Route::post('sessions/{id}/open', [SessaoController::class, 'open'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.open');
        Route::post('sessions/{id}/close', [SessaoController::class, 'close'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.close');
        Route::post('sessions/{id}/cancel', [SessaoController::class, 'cancel'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.cancel');
        Route::patch('sessions/{id}', [SessaoController::class, 'update'])
            ->middleware(IdempotenciaMiddleware::class)->name('sessions.update');

        // Evidencia/arquivo (E08, design §6.5): download so por URL assinada de
        // curta duracao, apos autorizacao (`evidencia.ler`).
        Route::get('arquivos/{id}', [ArquivoController::class, 'show'])
            ->middleware('can:evidencia.ler')->name('arquivos.show');

        // Relatorio de presenca agregado (E06, RF-14): sem nomes de pessoas.
        Route::get('reports/attendance', [ReportController::class, 'attendance'])
            ->middleware('can:relatorio.ler')->name('reports.attendance');
    });
});
