# Matriz de autorização

**Status:** proposto · Base para o [entregável 5](../07-produto/entregaveis.md).

A implementação combina **RBAC** (papel) com **ABAC** (escopo territorial, vínculo ativo, finalidade e propriedade do recurso). Todo endpoint e toda consulta ao banco aplicam a autorização; esconder botões na interface não basta. Testes automáticos cobrem acesso horizontal (outra unidade do mesmo nível) e vertical (nível acima).

## Matriz inicial

Legenda: **G** gerir · **R** registrar · **L** ler · **A** agregado (sem identificar pessoas) · **—** sem acesso · **\*** só com finalidade registrada.

| Recurso | Super Admin | Adm. Regional | Adm. Setor | Adm. Igreja | Ministério Local | Coordenadora | Auxiliar | Consulta e Ministérios (Central, Regional, Setorial) | Responsável |
|---|---|---|---|---|---|---|---|---|---|
| Estrutura organizacional | G | G (regional) | G (setor) | L | L | L | L | L | — |
| Usuários e vínculos de acesso | G | G (regional) | G (setor) | G (igreja) | — | L (EBI) | — | — | — |
| Participante: identificação | L\* | L\* | L\* | L (igreja) | L (igreja) | L (EBI) | L (EBI) | A | L (dependentes) |
| Participante: cadastro | G\* | — | — | G (igreja) | — | R, cadastro assistido | R | — | R (dependentes) |
| Participante: mesclar e corrigir | G\* | — | — | — | — | G (escopo a confirmar) | — | — | corrige dados permitidos |
| Exceção inclusiva: status | L\* | L | L | L | L | L | L | — | L (dependentes) |
| Exceção inclusiva: decisão | — | — | — | — | — | G (com o responsável) | — | — | solicita e concorda |
| Dado sensível (laudo) | — | — | — | — | — | L\* (EBI) | — | — | L (dependentes) |
| Agenda e sessões | L | L | L | G | L | G (EBI) | L (EBI) | A | L (sessões do dependente) |
| Presença | L\* | L\* | L\* | L | L | R (EBI) | R (EBI) | A | L (dependentes) |
| Estorno de presença | — | — | — | R | — | R (EBI) | — | — | — |
| Escala | L | L | L | G | L | G (EBI); aprova se chefe | L (própria) | A | — |
| Feedback | — | — | — | L | modera e libera | R (EBI) | R (EBI) | — | L (liberados, dependentes) |
| Biblioteca | G | moderação | moderação | L | L | R | R | L | — |
| Relatórios | A + L\* | A + L\* | A + L\* | A + L | A (igreja) | A (EBI) | — | A | — |
| Auditoria | L | L (regional) | L (setor) | — | — | — | — | — | — |

"Chefe" = atribuição de Coordenadora chefe ([ADR-0010](../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md)). Mudanças da versão 2.2: coluna Ministério Local ([ADR-0008](../06-arquitetura/decisoes/ADR-0008-moderacao-de-feedback.md)); coordenadora mescla, corrige e faz cadastro assistido ([ADR-0007](../06-arquitetura/decisoes/ADR-0007-cadastro-e-correcao-de-participantes.md), [ADR-0015](../06-arquitetura/decisoes/ADR-0015-cadastro-assistido.md)); coordenadora decide a exceção com o responsável ([ADR-0012](../06-arquitetura/decisoes/ADR-0012-excecao-decidida-pela-coordenadora.md)). Versão 2.3: Ministério da Central, Regional e Setorial têm a mesma visão da Consulta do seu nível ([ADR-0017](../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md)).

## Operações implementadas

Cada operação é um gate `can:<operacao>` nas rotas, decidido por **papel × operação** (RBAC, derivado da matriz) **e** escopo territorial (ABAC: o recurso está na subárvore do vínculo). `super_admin` ignora o escopo territorial (bypass explícito). Operações de leitura de estrutura/equipe verificam o papel; as de escrita verificam também o escopo quando há recurso-alvo.

| Operação (gate) | Papéis com acesso | Rotas | Escopo |
|---|---|---|---|
| `organizacao.ler` | super_admin, adm_regional, adm_setor, adm_igreja, coordenadora, auxiliar, ministerio_* | `GET /churches`, `/churches/{id}`, `/regions`, `/regions/{id}/sectors`, `/sectors`, `/ebis`, `/central` | papel |
| `organizacao.gerenciar` | super_admin, adm_regional, adm_setor | `POST/PATCH` de igreja, regional, setor, EBI; `POST /churches/{id}/transfers` | papel + escopo (subárvore) |
| `usuario.ler` | super_admin, adm_regional, adm_setor, adm_igreja, coordenadora | `GET /users`, `/users/{id}` | papel + listagem filtrada pela subárvore |
| `usuario.gerenciar` | super_admin, adm_regional, adm_setor, adm_igreja | `PATCH /users/{id}` | papel |
| `convite.gerenciar` | super_admin, adm_regional, adm_setor, adm_igreja | `GET/POST /invitations`, `DELETE /invitations/{id}` | papel + autoridade (papel e escopo do convite) |
| `acesso.gerenciar` | super_admin, adm_regional, adm_setor, adm_igreja | `POST /access-grants`, `DELETE /access-grants/{id}` | papel |
| `consentimento.ler` | super_admin, adm_regional, adm_setor, adm_igreja | `GET /privacy/consents/{id}` | papel + subárvore do titular |
| `auditoria.ler` | super_admin, adm_regional, adm_setor | `GET /audit-events` | papel + filtro territorial por evento |
| `agenda.gerenciar` | adm_igreja, coordenadora | `GET/POST /ebis/{id}/agenda`, `PATCH/DELETE /agenda/{id}`, `GET/POST /ebis/{id}/agenda-exceptions`, `DELETE /agenda-exceptions/{id}` | papel + escopo (subárvore) |
| `sessao.ler` | super_admin, adm_regional, adm_setor, adm_igreja, coordenadora, auxiliar, ministerio_local | `GET /ebis/{id}/sessions`, `GET /sessions/{id}`, `GET /sessions/{id}/participants` | papel + escopo (subárvore) |
| `sessao.gerenciar` | adm_igreja, coordenadora | `POST /ebis/{id}/sessions/generate`, `PATCH /sessions/{id}`, `POST /sessions/{id}/open`, `/close`, `/cancel` | papel + escopo (subárvore) |
| `presenca.ler` | super_admin, adm_regional, adm_setor, adm_igreja, coordenadora, auxiliar, ministerio_local | `GET /sessions/{id}/attendance`, `GET /participants/{id}/attendance` | papel + escopo (subárvore) |
| `presenca.registrar` | coordenadora, auxiliar | `POST /sessions/{id}/attendance`, `/sessions/{id}/contingency-attendance`, `POST /participants/{id}/attendance` | papel + escopo (subárvore) |
| `presenca.estornar` | adm_igreja, coordenadora | `POST /attendance/{id}/reversal` | papel + escopo (subárvore) |

Notas: o `*` (finalidade registrada) ainda não é aplicado — fora do escopo da Etapa 3 ([ADR-0022](../06-arquitetura/decisoes/ADR-0022-identidade-e-acesso.md)). A listagem de `/users` já filtra por subárvore; as demais listagens de organização ainda são apenas por papel (dívida registrada). As operações `consentimento.ler` e `auditoria.ler` chegaram com a **Etapa 4 (sub-etapa A1)** ([ADR-0030](../06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md)).

## A validar
- A matriz é uma proposta para revisão. Acima da igreja, só dados agregados; acesso identificado só por exceção com finalidade, prazo e aprovação do DPO ([ADR-0029](../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md), aguarda CCB e DPO).
- Acesso da coordenadora ao laudo, quando existir: proposta de leitura só com finalidade registrada.
