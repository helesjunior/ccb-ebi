# CatÃ¡logo de endpoints

> **Fonte da verdade:** a partir do desenvolvimento, o contrato Ã© o [openapi.yaml](openapi.yaml) (*contract-first*, ver [plano](../07-produto/plano-de-desenvolvimento.md)). Esta pÃ¡gina Ã© o mapa de planejamento: cada rota que entra no OpenAPI Ã© marcada aqui como `no OpenAPI`, e rotas novas entram primeiro no OpenAPI. O script [`scripts/check-endpoints.py`](../../scripts/check-endpoints.py) roda no CI e falha se uma operaÃ§Ã£o do OpenAPI nÃ£o estiver marcada aqui, ou se uma rota marcada nÃ£o existir no OpenAPI. Quando todas as rotas estiverem no OpenAPI, esta pÃ¡gina pode ser aposentada ou gerada a partir dele.

Proposta inicial, prefixo `/api/v1`. Rotas marcadas *novo* nÃ£o existiam na versÃ£o anterior e cobrem requisitos que nÃ£o tinham rota.

## AutenticaÃ§Ã£o
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| POST | `/auth/register-guardian` | Criar conta de responsÃ¡vel (era `register-parent`) | RF-03 |
| POST | `/auth/verify-email` | Verificar e-mail | RF-03 |
| POST | `/auth/login` | Autenticar (`no OpenAPI`) | â€” |
| POST | `/auth/mfa/verify` | Validar MFA | â€” |
| POST | `/auth/refresh` | Renovar sessÃ£o *novo* | â€” |
| POST | `/auth/logout` | Encerrar sessÃ£o *novo* (`no OpenAPI`) | â€” |
| POST | `/auth/password/forgot` | Pedir recuperaÃ§Ã£o *novo* | â€” |
| POST | `/auth/password/reset` | Redefinir senha *novo* | â€” |
| GET | `/me/scopes` | Contextos de atuaÃ§Ã£o disponÃ­veis *novo* | RN-11 |
| GET | `/me` | Ator autenticado (`no OpenAPI`) | â€” |
| GET | `/health` | Estado dos serviÃ§os (`no OpenAPI`) | â€” |

## OrganizaÃ§Ã£o
| MÃ©todo | Rota | Finalidade | RF |
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
| GET | `/churches/nearby?lat=&lon=` | Sugerir igrejas prÃ³ximas, com limites de uso | RF-08 |

## UsuÃ¡rios e equipe
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| GET/POST | `/users` | UsuÃ¡rios *novo* | RF-05 |
| POST | `/invitations` | Convidar *novo* | RF-05 |
| POST/DELETE | `/access-grants` | Conceder e revogar vÃ­nculo de acesso *novo* | RF-05 |

## Participantes e responsÃ¡veis
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/me/participants` | Dependentes vinculados (era `/me/children`) | RF-13 |
| GET | `/me/participants/{id}/attendance` | HistÃ³rico do dependente *novo* | RF-13 |
| POST | `/participants` | Solicitar cadastro (era `/children`) | RF-02 |
| GET | `/participants?q=` | Pesquisa no escopo do operador *novo* | RF-02 |
| POST | `/participants/{id}/guardians` | Solicitar vÃ­nculo | RF-03 |
| PATCH | `/guardian-requests/{id}` | Aprovar, recusar, contestar ou revogar *novo* | RF-03 |
| POST | `/participants/{id}/age-exceptions` | Solicitar exceÃ§Ã£o inclusiva *novo* | RF-02 |
| PATCH | `/age-exceptions/{id}` | Coordenadora decide (com o responsÃ¡vel), suspende ou revoga *novo* | RF-02 |
| POST | `/participants/assisted` | Cadastro assistido pela coordenadora *novo (2.2)* | RF-02 |
| POST | `/participants/{id}/merge` | Mesclar duplicidade (coordenadora) *novo (2.2)* | RF-02 |

## Agenda, sessÃµes e presenÃ§a
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| GET/POST | `/ebis/{id}/agenda` | ProgramaÃ§Ã£o *novo* | RF-06 |
| GET | `/ebis/{id}/sessions` | SessÃµes | RF-06 |
| POST | `/sessions/{id}/open`, `/close`, `/cancel` | Ciclo da sessÃ£o *novo* | RF-06 |
| POST | `/sessions/{id}/attendance` | Registrar presenÃ§a, idempotente (`no OpenAPI`) | RF-08 |
| POST | `/attendance/{id}/reversal` | Estornar presenÃ§a *novo* | RF-08 |
| GET | `/ebis/{id}/schedules` | Consultar escala (era `/roster`) | RF-07 |
| POST | `/sessions/{id}/schedules` | Criar escala *novo* | RF-07 |
| POST | `/schedules/{id}/approve` | AprovaÃ§Ã£o pela coordenadora chefe *novo (2.2)* | RF-07 |
| POST/PATCH | `/schedule-swaps` | Pedir e aprovar troca *novo* | RF-07 |
| POST | `/sessions/{id}/feedback` | Registrar feedback | RF-09 |
| POST | `/feedback/{id}/moderation` | MinistÃ©rio Local libera ou devolve *novo* | RF-09 |

## Biblioteca
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/activities` | Pesquisar | RF-10 |
| POST | `/activities` | Cadastrar atividade | RF-10 |
| POST | `/activities/{id}/versions` | Enviar nova versÃ£o | RF-10 |
| POST | `/activities/{id}/reports` | Denunciar *novo* | RF-10 |
| PATCH | `/activities/{id}/moderation` | Aprovar, recusar, retirar *novo* | RF-10 |
| POST | `/sessions/{id}/activity-uses` | Confirmar utilizaÃ§Ã£o | RF-11 |
| POST | `/activity-uses/{id}/ratings` | Avaliar | RF-12 |

## RelatÃ³rios, privacidade e auditoria
| MÃ©todo | Rota | Finalidade | RF |
|---|---|---|---|
| GET | `/reports/attendance` | Indicadores de presenÃ§a | RF-14 |
| GET | `/reports/participants` | Indicadores de participantes *novo* | RF-14 |
| GET/POST | `/privacy/consents` | Consultar e registrar manifestaÃ§Ãµes | RF-04 |
| POST | `/privacy/requests` | ExercÃ­cio de direitos | RF-04, RF-16 |
| GET | `/audit-events` | Consultar auditoria *novo* | RF-16 |
