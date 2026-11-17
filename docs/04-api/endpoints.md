# Catálogo de endpoints

> **Fonte da verdade:** a partir do desenvolvimento, o contrato é o [openapi.yaml](openapi.yaml) (*contract-first*, ver [plano](../07-produto/plano-de-desenvolvimento.md)). Esta página é o mapa de planejamento: cada rota que entra no OpenAPI é marcada aqui como `no OpenAPI`, e rotas novas entram primeiro no OpenAPI. O script [`scripts/check-endpoints.py`](../../scripts/check-endpoints.py) roda no CI e falha se uma operação do OpenAPI não estiver marcada aqui, ou se uma rota marcada não existir no OpenAPI. Quando todas as rotas estiverem no OpenAPI, esta página pode ser aposentada ou gerada a partir dele.

Proposta inicial, prefixo `/api/v1`. Rotas marcadas *novo* não existiam na versão anterior e cobrem requisitos que não tinham rota.

## Autenticação
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| POST | `/auth/register-guardian` | Criar conta de responsável exigindo aceite do termo vigente (`no OpenAPI`) | RF-03, CA-16 |
| POST | `/auth/verify-email` | Verificar e-mail (`no OpenAPI`) | RF-03 |
| POST | `/auth/email/resend` | Reenviar verificação de e-mail (`no OpenAPI`) | RF-03 |
| POST | `/auth/login` | Autenticar (`no OpenAPI`) | — |
| POST | `/auth/mfa/verify` | Validar MFA (`no OpenAPI`) | — |
| POST | `/auth/refresh` | Renovar sessão *novo* | — |
| POST | `/auth/logout` | Encerrar sessão *novo* (`no OpenAPI`) | — |
| POST | `/auth/password/forgot` | Pedir recuperação *novo* (`no OpenAPI`) | — |
| POST | `/auth/password/reset` | Redefinir senha *novo* (`no OpenAPI`) | — |
| POST | `/me/mfa/totp` | Inscrever TOTP *novo* (`no OpenAPI`) | RF-05 |
| POST | `/me/mfa/totp/confirm` | Confirmar inscrição TOTP *novo* (`no OpenAPI`) | RF-05 |
| POST | `/me/mfa/recovery-codes` | Gerar códigos de recuperação *novo* (`no OpenAPI`) | RF-05 |
| GET | `/me/sessions` | Listar dispositivos *novo* (`no OpenAPI`) | RF-05 |
| DELETE | `/me/sessions/{id}` | Revogar dispositivo *novo* (`no OpenAPI`) | RF-05 |
| GET | `/me/scopes` | Contextos de atuação disponíveis *novo* (`no OpenAPI`) | RN-11 |
| GET | `/me` | Ator autenticado (`no OpenAPI`) | — |
| PATCH | `/me` | Atualizar meu perfil (`no OpenAPI`) | RF-05 |
| POST | `/me/password` | Trocar minha senha (`no OpenAPI`) | RF-05 |
| GET | `/health` | Estado dos serviços (`no OpenAPI`) | — |

## Organização
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/regions/{id}/sectors` | Setores de uma regional (`no OpenAPI`) | RF-01 |
| GET/POST | `/regions` | Listar e criar regional (`no OpenAPI`) | RF-01 |
| PATCH | `/regions/{id}` | Atualizar regional (`no OpenAPI`) | RF-01 |
| GET/POST | `/sectors` | Listar e criar setor (`no OpenAPI`) | RF-01 |
| PATCH | `/sectors/{id}` | Atualizar setor (`no OpenAPI`) | RF-01 |
| GET | `/central` | Central Nacional (`no OpenAPI`) | RF-01 |
| GET/POST | `/churches` | Listar e criar igreja (`no OpenAPI`) | RF-01 |
| GET/PATCH | `/churches/{id}` | Ver e atualizar igreja (`no OpenAPI`) | RF-01 |
| POST | `/churches/{id}/transfers` | Transferir igreja de setor (`no OpenAPI`) | RF-01 |
| GET/POST | `/ebis` | Listar e criar EBI (`no OpenAPI`) | RF-01 |
| PATCH | `/ebis/{id}` | Atualizar EBI (`no OpenAPI`) | RF-01 |
| GET | `/churches/nearby?lat=&lon=` | Sugerir igrejas próximas, com limites de uso | RF-08 |

## Usuários e equipe
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/users` | Listar usuários da equipe *novo* (`no OpenAPI`) | RF-05 |
| GET/PATCH | `/users/{id}` | Ver e atualizar usuário *novo* (`no OpenAPI`) | RF-05 |
| GET/POST | `/invitations` | Listar e convidar *novo* (`no OpenAPI`) | RF-05 |
| DELETE | `/invitations/{id}` | Cancelar convite *novo* (`no OpenAPI`) | RF-05 |
| POST | `/invitations/{id}/accept` | Aceitar convite (público, token único) *novo* (`no OpenAPI`) | RF-05 |
| POST | `/access-grants` | Conceder vínculo de acesso *novo* (`no OpenAPI`) | RF-05 |
| DELETE | `/access-grants/{id}` | Revogar vínculo de acesso *novo* (`no OpenAPI`) | RF-05 |

## Participantes e responsáveis
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/me/participants` | Dependentes vinculados (era `/me/children`) (`no OpenAPI`) | RF-13 |
| GET | `/me/participants/{id}/attendance` | Histórico do dependente *novo* | RF-13 |
| POST | `/participants` | Solicitar cadastro (era `/children`) (`no OpenAPI`) | RF-02 |
| GET | `/participants` | Pesquisa no escopo do operador *novo* (`no OpenAPI`) | RF-02 |
| GET | `/participants/{id}` | Consultar participante (`no OpenAPI`) | RF-02 |
| POST | `/participants/{id}/guardians` | Solicitar vínculo (`no OpenAPI`) | RF-03 |
| PATCH | `/guardian-requests/{id}` | Aprovar, recusar, contestar ou revogar *novo* (`no OpenAPI`) | RF-03 |
| POST | `/participants/{id}/age-exceptions` | Solicitar exceção inclusiva *novo* (`no OpenAPI`) | RF-02 |
| PATCH | `/age-exceptions/{id}` | Coordenadora decide (com o responsável), suspende ou revoga *novo* (`no OpenAPI`) | RF-02 |
| POST | `/participants/assisted` | Cadastro assistido pela coordenadora *novo (2.2)* (`no OpenAPI`) | RF-02 |
| POST | `/participants/{id}/merge` | Mesclar duplicidade (coordenadora) *novo (2.2)* (`no OpenAPI`) | RF-02 |
| POST | `/participants/{id}/merge/undo` | Desfazer mesclagem *novo (2.2)* (`no OpenAPI`) | RF-02 |
| GET | `/arquivos/{id}` | URL assinada de download da evidência *novo (2.2)* (`no OpenAPI`) | RF-02 |

## Agenda, sessões e presença
| Método | Rota | Finalidade | RF |
|---|---|---|---|
| POST | `/sessions/{id}/attendance` | Registrar presença, idempotente (`no OpenAPI`) | RF-08 |
| POST | `/attendance/{id}/reversal` | Estornar presença *novo* | RF-08 |
| GET | `/ebis/{id}/schedules` | Consultar escala (era `/roster`) | RF-07 |
| POST | `/sessions/{id}/schedules` | Criar escala *novo* | RF-07 |
| POST | `/schedules/{id}/approve` | Aprovação pela coordenadora chefe *novo (2.2)* | RF-07 |
| POST/PATCH | `/schedule-swaps` | Pedir e aprovar troca *novo* | RF-07 |
| POST | `/sessions/{id}/feedback` | Registrar feedback | RF-09 |
| POST | `/feedback/{id}/moderation` | Ministério Local libera ou devolve *novo* | RF-09 |
| GET | `/me/ebis` | EBIs que o usuário pode operar *novo* (`no OpenAPI`) | RF-06 |
| GET/POST | `/ebis/{id}/agenda` | Faixas semanais do EBI *novo* (`no OpenAPI`) | RF-06 |
| PATCH/DELETE | `/agenda/{id}` | Alterar/encerrar faixa *novo* (`no OpenAPI`) | RF-06 |
| GET/POST | `/ebis/{id}/agenda-exceptions` | Exceções da agenda *novo* (`no OpenAPI`) | RF-06 |
| DELETE | `/agenda-exceptions/{id}` | Remover exceção *novo* (`no OpenAPI`) | RF-06 |
| GET | `/ebis/{id}/sessions` | Sessões do EBI no período (`no OpenAPI`) | RF-06 |
| POST | `/ebis/{id}/sessions/generate` | Gerar sessões previstas *novo* (`no OpenAPI`) | RF-06 |
| GET/PATCH | `/sessions/{id}` | Detalhe e correção da sessão *novo* (`no OpenAPI`) | RF-06 |
| POST | `/sessions/{id}/open`, `/sessions/{id}/close`, `/sessions/{id}/cancel` | Ciclo da sessão *novo* (`no OpenAPI`) | RF-06 |

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
| GET/POST | `/privacy/consents` | Consultar e registrar manifestações do próprio titular (`no OpenAPI`) | RF-04 |
| GET | `/privacy/consents/{id}` | Recuperar manifestação com a versão do termo (`no OpenAPI`) | RF-04, CA-16 |
| GET | `/privacy/terms` | Consultar termo vigente por finalidade, com o texto integral para leitura antes do aceite (`no OpenAPI`) | RF-04, CA-16 |
| POST | `/privacy/requests` | Exercício de direitos (`no OpenAPI`) | RF-04, RF-16 |
| GET | `/audit-events` | Consultar auditoria *novo* (`no OpenAPI`) | RF-16 |
