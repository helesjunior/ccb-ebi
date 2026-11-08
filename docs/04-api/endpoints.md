# Catálogo de endpoints

Proposta inicial, prefixo `/api/v1`. Rotas marcadas *novo* não existiam na versão anterior e cobrem requisitos que não tinham rota.

## Autenticação
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| POST | `/auth/register-guardian` | Criar conta de responsável (era `register-parent`) | RF-03 |
| POST | `/auth/verify-email` | Verificar e-mail | RF-03 |
| POST | `/auth/login` | Autenticar | — |
| POST | `/auth/mfa/verify` | Validar MFA | — |
| POST | `/auth/refresh` | Renovar sessão *novo* | — |
| POST | `/auth/logout` | Encerrar sessão *novo* | — |
| POST | `/auth/password/forgot` | Pedir recuperação *novo* | — |
| POST | `/auth/password/reset` | Redefinir senha *novo* | — |
| GET | `/me/scopes` | Contextos de atuação disponíveis *novo* | RN-11 |

## Organização
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/regions/{id}/sectors` | Setores autorizados | RF-01 |
| GET/POST/PATCH | `/churches`, `/churches/{id}` | Igrejas *novo* | RF-01 |
| POST | `/churches/{id}/transfers` | Transferir igreja de setor *novo* | RF-01 |
| GET/POST/PATCH | `/ebis`, `/ebis/{id}` | EBIs *novo* | RF-01 |
| GET | `/churches/nearby?lat=&lon=` | Sugerir igrejas próximas, com limites de uso | RF-08 |

## Usuários e equipe
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET/POST | `/users` | Usuários *novo* | RF-05 |
| POST | `/invitations` | Convidar *novo* | RF-05 |
| POST/DELETE | `/access-grants` | Conceder e revogar vínculo de acesso *novo* | RF-05 |

## Participantes e responsáveis
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/me/participants` | Dependentes vinculados (era `/me/children`) | RF-13 |
| GET | `/me/participants/{id}/attendance` | Histórico do dependente *novo* | RF-13 |
| POST | `/participants` | Solicitar cadastro (era `/children`) | RF-02 |
| GET | `/participants?q=` | Pesquisa no escopo do operador *novo* | RF-02 |
| POST | `/participants/{id}/guardians` | Solicitar vínculo | RF-03 |
| PATCH | `/guardian-requests/{id}` | Aprovar, recusar, contestar ou revogar *novo* | RF-03 |
| POST | `/participants/{id}/age-exceptions` | Solicitar exceção inclusiva *novo* | RF-02 |
| PATCH | `/age-exceptions/{id}` | Decidir, suspender ou revogar *novo* | RF-02 |

## Agenda, sessões e presença
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET/POST | `/ebis/{id}/agenda` | Programação *novo* | RF-06 |
| GET | `/ebis/{id}/sessions` | Sessões | RF-06 |
| POST | `/sessions/{id}/open`, `/close`, `/cancel` | Ciclo da sessão *novo* | RF-06 |
| POST | `/sessions/{id}/attendance` | Registrar presença (idempotente) | RF-08 |
| POST | `/attendance/{id}/reversal` | Estornar presença *novo* | RF-08 |
| GET | `/ebis/{id}/schedules` | Consultar escala (era `/roster`) | RF-07 |
| POST | `/sessions/{id}/schedules` | Criar escala *novo* | RF-07 |
| POST/PATCH | `/schedule-swaps` | Pedir e aprovar troca *novo* | RF-07 |
| POST | `/sessions/{id}/feedback` | Registrar feedback | RF-09 |
| POST | `/feedback/{id}/release` | Liberar feedback *novo* | RF-09 |

## Biblioteca
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/activities` | Pesquisar | RF-10 |
| POST | `/activities` | Cadastrar atividade | RF-10 |
| POST | `/activities/{id}/versions` | Enviar nova versão | RF-10 |
| POST | `/activities/{id}/reports` | Denunciar *novo* | RF-10 |
| PATCH | `/activities/{id}/moderation` | Aprovar, recusar, retirar *novo* | RF-10 |
| POST | `/sessions/{id}/activity-uses` | Confirmar utilização | RF-11 |
| POST | `/activity-uses/{id}/ratings` | Avaliar | RF-12 |

## Relatórios, privacidade e auditoria
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/reports/attendance` | Indicadores de presença | RF-14 |
| GET | `/reports/participants` | Indicadores de participantes *novo* | RF-14 |
| GET/POST | `/privacy/consents` | Consultar e registrar manifestações | RF-04 |
| POST | `/privacy/requests` | Exercício de direitos | RF-04, RF-16 |
| GET | `/audit-events` | Consultar auditoria *novo* | RF-16 |
