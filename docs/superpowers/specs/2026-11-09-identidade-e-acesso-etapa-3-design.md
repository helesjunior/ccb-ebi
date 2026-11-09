# Desenho — Identidade e acesso (Etapa 3 / E01)

**Data:** 09/11/2026 · **Status:** proposto, aguardando aprovação
**Abrange:** Etapa 3 (Identidade e acesso, E01) do [plano de desenvolvimento](../../07-produto/plano-de-desenvolvimento.md)

## 1. Intenção e o porquê

As Etapas 0–2 entregaram a fundação executável e a espinha dorsal organizacional. O acesso ainda é um **andaime**: o `AutenticadorFicticio` autentica contra um ator semeado e a autorização é um único `Gate` provisório (`gerir-organizacao` ⇔ `papel === 'coordenadora'`). A Etapa 2 registrou explicitamente que **a Etapa 3 substitui esse gate por RBAC+ABAC real e `X-Acting-Scope`**, e que os testes de autorização daquela etapa viram o alicerce dos testes da matriz.

Esta leva torna a identidade e o acesso reais:

- **Autenticação real** com e-mail verificado, senha forte e recuperação de senha sem enumeração de contas.
- **MFA obrigatório** para todo perfil que não seja Responsável (CA-15): TOTP, códigos de recuperação e código por e-mail como contingência.
- **Autorização RBAC+ABAC** conforme a [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md): papel **e** escopo territorial/vínculo ativo, com **acúmulo de papéis** e escolha de contexto por `X-Acting-Scope` revalidado no servidor (RN-11, CA-03).
- **Equipe do EBI (RF-05):** convite, nomeações/afastamentos/desligamentos com histórico; usuário desligado perde o acesso.
- **E-mail transacional pela fila** (Horizon já existe desde a Etapa 1).

**Identidade "in-place" (decidido com o responsável do produto).** A tabela `atores` deixa de ser um andaime e **evolui para a entidade `usuario` real**, com ID opaco (ULID), unificando tudo numa entidade só. Não convivem duas entidades de identidade. Isso resolve, de uma vez, a dívida registrada no desenho da Etapa 2: *"a fundação expõe `atores` como `bigint`, enquanto esta leva usa ULID. Reconciliar o padrão de IDs opacos..."*.

## 2. Objetivos, não objetivos e anti-objetivos

**Objetivos**
- Entidade `usuario` (ULID) com e-mail normalizado e verificado, senha (Argon2id) **ou** identidade federada, status.
- Fluxos de autenticação: cadastro, verificação de e-mail, login (com desafio de MFA), logout, recuperação e redefinição de senha.
- MFA: `fator_mfa` (TOTP, e-mail), `codigo_recuperacao`, `sessao_login` (por dispositivo, revogável e com expiração por perfil).
- Autorização: `papel`, `vinculo_acesso` (papel + escopo + vigência), `ContextoAtuacao` resolvido a partir de `X-Acting-Scope`, Policies como superfície e um núcleo único de escopo (Abordagem C).
- Equipe do EBI (RF-05): `convite` e vínculos de acesso geridos (nomeação, afastamento, desligamento com histórico).
- E-mail transacional por fila (verificação, convite, redefinição, código de contingência).
- Auditoria: login, falha de autenticação e mudança de permissão geram evento (RN-10) na trilha já existente.
- Todos os endpoints novos entram no [openapi.yaml](../../04-api/openapi.yaml) **e** no [endpoints.md](../../04-api/endpoints.md) (fonte da verdade → catálogo → código).

**Não objetivos (nesta leva)**
- Passkeys/WebAuthn (o plano diz "podem ficar para depois do MVP").
- Identidade federada da CCB (o modelo a prevê como coluna, mas a integração real não entra).
- WhatsApp como canal de MFA (só após análise de risco; o plano não exige agora).
- Termos versionados e consentimento (RF-04 é E05).
- Trilha de auditoria consultável, retenção/expurgo (E05).
- **Finalidade registrada** (o `*` da matriz): a captura de finalidade na autorização é de E05 (privacidade) e depende de D-10. Nesta etapa as Policies decidem por papel + escopo territorial; as linhas `*` da matriz ficam cobertas só na dimensão papel/escopo, com a finalidade registrada como dívida explícita.
- Regras de negócio de Participantes/Presença (E03/E04); escopo do Responsável sobre dependentes.

**Anti-objetivos (o que faria esta leva falhar mesmo "funcionando")**
- `X-Acting-Scope` aceito sem revalidação no servidor (permitiria acesso horizontal/vertical indevido).
- Enumeração de contas na recuperação de senha (mensagem ou tempo que revelam se o e-mail existe).
- MFA contornável: sessão operacional emitida antes do segundo fator estar validado.
- Segredos TOTP em texto claro; códigos de recuperação ou tokens em log.
- Autorização só na borda (esconder no frontend), sem revalidar em cada recurso.
- Perder o histórico de nomeações/afastamentos/desligamentos (RF-05.3).
- A `atores` migrada com dados de produção reais quebrados (não há produção; mas a migração deve ser reversível e testada).

## 3. Restrições

- **PHP 8.4 em contêiner** (`docker compose run --rm app ...`); nunca o PHP do host.
- **PostgreSQL 17 / Redis 7**; fila e cache em Redis; Horizon já configurado.
- **Monólito modular (ADR-0002):** as classes de identidade vivem em `App\Domain\Identidade\*`; `app/Http/` só consome **contratos** (`App\Domain\Identidade\Contracts\*`), nunca modelos internos. O `ModularBoundariesTest` da Etapa 1/2 continua verde.
- **IDs opacos (ULID)** conforme [convenções](../../04-api/convencoes.md); rotas e campos em inglês, domínio em português.
- **problem+json (RFC 9457)** com `code` interno; paginação por cursor; `Idempotency-Key` nas escritas críticas; rate limit por usuário e IP.
- **Sanctum** já é o guard (`auth:sanctum`); o provider `atores` passa a apontar para a nova entidade `usuario`.
- **Auditoria append-only com cadeia de hash** já existe: o novo uso apenas grava eventos; não altera o mecanismo.
- **Nenhum dado real** antes da Etapa 8.

## 4. Abordagens consideradas

A complexidade desta etapa está na **autorização e no contexto de atuação**. As três formas de estruturá-la foram avaliadas na conversa:

**A) Policies + `X-Acting-Scope` validado por middleware, regra espalhada.** Reaproveita tudo o que existe, mas deixa a lógica de escopo dividida entre middleware e Policies — fonte provável de vazamento quando ministérios e acúmulo entrarem. **Descartada.**

**B) Camada de autorização própria (serviço + catálogo de permissões), sem Policies.** Concentra a regra, mas descarta o `Gate`/`can:` já firmado nas Etapas 1–2 e reimplementa o que o framework dá; mais código e superfície. **Descartada.**

**C) Híbrida — Policies como superfície, núcleo único de escopo (escolhida).** As Policies continuam sendo a superfície (`can:` que as rotas já usam) e **delegam** para um serviço `ResolverEscopo` (o núcleo do ABAC territorial/vínculo). O middleware só valida o cabeçalho e injeta o contexto. Mantém a ergonomia existente sem reimplementar o Laravel e concentra a complexidade (horizontal/vertical, ministério, acúmulo) num ponto testável isoladamente.

**Decisão:** Abordagem **C**. O responsável do produto escolheu "siga com a C".

## 5. Estrutura e decisões de como construir

### 5.1 Reconciliar `atores` → `usuario` (in-place)

Migração em passos, reversível:

1. **Nova migração** altera a tabela (renomeia `atores` → `usuarios` ou cria `usuarios` e migra — decisão do construtor; o critério é: uma entidade só ao fim, sem tabela `atores` remanescente e sem `App\Models\User` órfão).

   *Proposta do construtor a decidir no plano:* **renomear** `atores` para `usuarios` e alterar a PK para ULID exige recriar a tabela; mais simples é **criar `usuarios` com a forma final** e copiar as linhas de `atores` (há só o ator semeado), depois dropar `atores`. Como não há produção, o risco é baixo e o resultado é limpo.

2. **Colunas finais de `usuario`** (do [modelo conceitual](../../03-dados/modelo-conceitual.md)):
   `id` (ULID/crockford), `nome`, `email_normalizado` UNIQUE, `email_verificado_em`, `senha` (hash Argon2id, nullable quando federado), `identidade_federada` (nullable), `status` (`ativo`/`desligado`/…), timestamps.

3. **`papel` deixa de ser string única no usuário.** A coluna `papel` sai de `usuario`; os papéis passam a viver em `vinculo_acesso`. O seeder e os testes de autorização passam a criar vínculos (ver 5.4).

4. **`App\Models\User`** padrão do Laravel é removido junto com a tabela `users` de esqueleto, se existir, e o `config/auth.php` aponta o provider para `App\Domain\Identidade\Models\Usuario`.

5. **`AutenticadorFicticio`** é substituído por um `Autenticador` real (contrato `AutenticarAtor` evolui/some). Os endpoints `POST /auth/login`, `POST /auth/logout`, `GET /me` mudam de contrato (ver 5.5) e o OpenAPI/catálogo são atualizados.

> **Onde o modelo vive:** `App\Domain\Identidade\Models\Usuario`. Nada fora do módulo identidade importa esse modelo; o resto consome contratos.

### 5.2 Entidades novas (módulo `Identidade`)

| Tabela | Campos (modelo conceitual) | Notas |
|---|---|---|
| `usuario` | id, nome, email_normalizado, email_verificado_em, senha_hash/identidade_federada, status | ULID; `email_normalizado` UNIQUE |
| `papel` | id, código, descrição | catálogo (seeder) derivado da [matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) + [ADR-0017](../../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md): `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja`, `ministerio_local`, `ministerio_regional`, `ministerio_setorial`, `ministerio_central`, `coordenadora`, `auxiliar`, `responsavel`. **Ministérios são papéis distintos por nível** (o ADR-0017 dá à Central um perfil nacional próprio); `super_admin` ignora escopo territorial (bypass explícito e auditado). |
| `vinculo_acesso` | id, usuario_id, papel_id, tipo_escopo, escopo_id, inicio, fim, status | vigência `[inicio, fim)` com `fim IS NULL` = ativo (mesma forma da `igreja_vinculacao` da Etapa 2) |
| `convite` | id, email, papel_id, escopo, criado_por, expira_em, status, token_hash | RF-05; token secreto de uso único (guardado como hash) |
| `fator_mfa` | id, usuario_id, tipo (totp, email), segredo_cifrado, confirmado_em, criado_em | segredo TOTP **cifrado** |
| `codigo_recuperacao` | id, usuario_id, hash, usado_em | hash, uso único |
| `sessao_login` | id, usuario_id, dispositivo, criada_em, expira_em, revogada_em | por dispositivo; expiração por perfil |

**`tipo_escopo`** é polimórfico textual com `escopo_id` opaco: `central`, `regional`, `setor`, `igreja`, `ebi`. Os escopos referenciam IDs do módulo `Organizacao` — mas o módulo `Identidade` **não importa modelos** da Organização: guarda o ID opaco e valida a existência via contrato da Organização quando necessário. O escopo por descendência (5.4) usa a hierarquia Central→Regional→Setor→Igreja→EBI para decidir se o recurso está sob o nó do vínculo. (Escopo do Responsável sobre dependentes é de E03/E05, fora desta etapa.)

### 5.3 Autenticação, MFA e sessão

**Contratos (módulo Identidade), consumidos por `app/Http`:**
- `RegistrarConta` — cadastro + disparo de verificação de e-mail.
- `VerificarEmail` — consome o token de verificação.
- `Autenticar` — valida credenciais; retorna o estágio (exige MFA ou concluído) e o usuário.
- `GerenciarMfa` — inscrever/confirmar TOTP, gerar/validar códigos de recuperação, enviar/validar código por e-mail.
- `GerenciarSessao` — abrir sessão de login (por dispositivo), listar e revogar.
- `RecuperarSenha` — solicitar/redefinir **sem enumeração**.
- `ConsultarIdentidade` — leitura para outros módulos/HTTP (DTOs, sem modelos).

**Implementação de TOTP.** Não há biblioteca de OTP instalada. **Proposta (decisão do construtor):** implementar TOTP (RFC 6238, HMAC-SHA1, janela ±1) numa classe pequena e bem testada do módulo, **sem** dependência nova, ou adotar `pragmarx/google2fa` se o construtor preferir biblioteca. O critério: testável com vetores RFC e sem trazer superfície desnecessária. A decisão vai no plano.

**Fluxo de login (MFA obrigatório).** `POST /auth/login` valida e-mail+senha e:
- se o perfil **exige MFA** (todo perfil ≠ Responsável) e ainda não há segundo fator **validado**, devolve **`mfa_required` + um desafio** (token curto, escopo restrito), **sem** emitir token de sessão plena;
- o desafio cobre **dois casos**: (a) o usuário **já tem** fator — valida via `POST /auth/mfa/verify`; (b) o usuário **não tem** fator (primeiro login do convidado) — o mesmo token restrito só permite **inscrever** o TOTP (`/me/mfa/totp` + `/me/mfa/totp/confirm`) e, confirmado, conclui;
- só após o fator estar **validado** é emitido o token Sanctum de sessão plena;
- Responsável (quando não exige MFA) conclui direto.

**Inscrever MFA no primeiro acesso é obrigatório.** Um membro de equipe convidado entra sem fator; o token restrito do desafio **não dá acesso à área operacional** — apenas à inscrição do fator. Isso cumpre CA-15 por construção (a área operacional nunca é alcançável antes do segundo fator).

**Expiração por perfil.** Sessão 12 h (Responsável) / 8 h (equipe) configurável; revogação por dispositivo; reautenticação para operações críticas (vincular responsável, alterar papel, exportar dados — lista fechada no plano).

**Recuperação de senha sem enumeração.** Resposta sempre idêntica (202) independentemente de o e-mail existir; token de uso único, validade curta, rate limit e tempo de resposta constante.

### 5.4 Autorização (RBAC + ABAC) — Abordagem C

**Decisões confirmadas com o responsável do produto:**

1. **O poder desce a hierarquia (escopo por descendência).** O `vinculo_acesso` aponta um nó (Central/Regional/Setor/Igreja/EBI); o ator **vê e gere aquele nó e todos os seus descendentes**. Ex.: `Adm. Setor` do Setor Norte gere as igrejas e EBIs sob ele; `Adm. Igreja` gere o EBI e os usuários daquela igreja. A matriz define **o quê** (G/R/L/A) em cada nível; o escopo define **onde** (o nó e abaixo). Assim, autorização é "o nó do recurso está dentro da subárvore do nó do vínculo?".
2. **Autorização por operação, não por gate único.** O `gerir-organizacao` único é substituído por **uma checagem por recurso/operação**: cada rota declara o tipo do recurso-alvo (regional, setor, igreja, EBI…) e a decisão combina papel + escopo + tipo do recurso. As rotas de escrita da Organização são reagrupadas por recurso.

- **`ResolverEscopo` (núcleo).** Dado um `usuario`, o `ContextoAtuacao` (papel + nó) e um recurso (tipo + id/nó), decide se o recurso está **dentro da subárvore** do nó do vínculo vigente **e** se o papel admite a operação naquele tipo de recurso (matriz). Respeita horizontal (não vê irmão fora da subárvore) e vertical (não age acima). É a única fonte da verdade do ABAC.
- **`ContextoAtuacao` + middleware `X-Acting-Scope`.** O middleware lê o cabeçalho (ex.: `papel=adm_setor;escopo=setor:<ulid>`), confere contra os vínculos vigentes do usuário e **rejeita** (`403`) contexto que não lhe pertence; injeta o `ContextoAtuacao` resolvido na request. Se o usuário tem **um** vínculo, o contexto é implícito; se tem vários, o cabeçalho é **obrigatório** (RN-11). O contrato `GET /me/scopes` (catálogo) lista os contextos disponíveis.
- **Policies (superfície).** `RegionalPolicy`, `SetorPolicy`, `IgrejaPolicy`, `EbiPolicy`, `UsuarioPolicy`, `VinculoAcessoPolicy`, `ConvitePolicy`, `EventoAuditoriaPolicy`… cada método (`view`, `create`, `update`, `manage`) chama o `ResolverEscopo`. As rotas seguem usando `can:`.
- **Substituir o gate provisório.** Os testes de autorização da Etapa 2 (hoje sobre `coordenadora`/`responsavel`) são **reescritos sobre a matriz** e continuam verdes em forma.
- **Acúmulo de papéis (RN-11).** Um usuário pode ter vários `vinculo_acesso` (ex.: Administrador de igreja **e** Ministério local, possivelmente em nós diferentes). A escolha de qual contexto usar numa requisição é do cliente via `X-Acting-Scope`; o servidor revalida que o contexto pedido corresponde a um vínculo vigente.

**Aplicação da matriz.** Cada linha da [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) cujo papel pertence a E01 vira teste "pode/não pode", incluindo **horizontal** (outra unidade do mesmo nível, fora da subárvore) e **vertical** (nível acima). As linhas marcadas `*` ("só com finalidade registrada") **não** entram nesta etapa: a captura de finalidade é de [E05 / privacidade](../../07-produto/plano-de-desenvolvimento.md) e depende de [D-10](../../06-arquitetura/decisoes/README.md); nesta etapa as Policies cobrem o papel e o escopo territorial, sem o parâmetro de finalidade. Isso fica registrado como dívida explícita.

### 5.5 Contratos de API (novos/alterados)

Os nomes das rotas **seguem o [catálogo](endpoints.md)** (o design inicial propunha nomes diferentes; corrigido). O OpenAPI é a fonte da verdade e o catálogo é o mapa: as rotas entram no `openapi.yaml` **e** no `endpoints.md`, e o `check-endpoints.py` fica verde.

**Autenticação/MFA**
| Método | Rota | Notas |
|---|---|---|
| `POST` | `/auth/register-guardian` | cadastro do Responsável; dispara verificação |
| `POST` | `/auth/verify-email` | consome token de verificação |
| `POST` | `/auth/email/resend` | reenvio (rate limited) — **rota nova**, entra no catálogo |
| `POST` | `/auth/login` | pode devolver `mfa_required` + desafio (já existe, contrato ampliado) |
| `POST` | `/auth/mfa/verify` | valida o segundo fator e/ou conclui a inscrição; emite a sessão plena |
| `POST` | `/auth/refresh` | renova a sessão (catálogo); revoga o token antigo |
| `POST` | `/auth/password/forgot` | recuperação sem enumeração |
| `POST` | `/auth/password/reset` | redefine com token |
| `POST` | `/auth/logout` | revoga a sessão atual (já existe) |
| `GET` | `/me` | usuário + status de MFA (já existe, contrato ampliado) |
| `GET` | `/me/scopes` | contextos de atuação disponíveis (RN-11) |

**MFA e sessões (autenticado)**
| Método | Rota | Notas |
|---|---|---|
| `POST` | `/me/mfa/totp` | inicia inscrição (devolve segredo/QR) |
| `POST` | `/me/mfa/totp/confirm` | confirma e ativa |
| `POST` | `/me/mfa/recovery-codes` | (re)gera códigos de recuperação |
| `GET` | `/me/sessions` | lista dispositivos |
| `DELETE` | `/me/sessions/{id}` | revoga dispositivo |

**Equipe do EBI (RF-05, conforme matriz)** — sob `can:` apropriado
| Método | Rota | Notas |
|---|---|---|
| `GET` | `/users` | lista (escopo da matriz) |
| `POST` | `/invitations` | convida (papel + escopo) |
| `GET` | `/invitations` | lista convites |
| `POST` | `/invitations/{id}/accept` | **rota não autenticada**: o token do convite (secreto, expira) é a credencial; cria usuário + vínculo e exige inscrição de MFA |
| `DELETE` | `/invitations/{id}` | revoga convite |
| `POST` | `/access-grants` | nomeação (papel + escopo + início) |
| `PATCH` | `/access-grants/{id}` | afastamento/desligamento (fim + status), com motivo |
| `GET` | `/users/{id}/access-grants` | histórico de vínculos |

> O `POST /invitations/{id}/accept` fica **fora** do grupo `auth:sanctum` (o convidado ainda não tem conta). O token do convite valida o aceite e é consumido uma única vez.

### 5.6 E-mail transacional por fila

Jobs/Notificações enfileirados (Horizon): verificação de e-mail, convite, redefinição de senha, código de contingência por e-mail. Em dev, Mailpit. Conteúdo sem dados pessoais em log.

### 5.7 Auditoria (RN-10)

Login, falha de autenticação, mudança de permissão/vínculo e desligamento gravam evento na trilha existente (`RegistrarEvento`), com `ator_id` (ULID) verdadeiro e `motivo` quando aplicável. Sem eventos de negócio fora do escopo.

### 5.8 Portal Web

Telas mínimas para o fluxo ponta a ponta: login (+ passo de MFA), cadastro/verificação, recuperação/redefinição, seleção de contexto quando há múltiplos vínculos, e gestão básica de equipe/convites para os perfis autorizados. Cliente gerado do OpenAPI (o codegen já funciona — ver correção do CI). Acessível (RNF-05).

### 5.9 Frontend: nada novo além das telas

O portal continua React+Vite+TS; nenhuma mudança de stack. As telas entram no mesmo padrão. Sem novas dependências além do que o fluxo exigir (ex.: nenhuma biblioteca de OTP no frontend; o TOTP é validado no servidor).

## 6. O que fica para o construtor decidir

- Renomear vs recriar a tabela na migração `atores`→`usuarios` (critério: uma entidade só, reversível, testada).
- TOTP nativo (RFC 6238) vs `pragmarx/google2fa` — com o critério acima.
- Formato exato do token de **desafio de MFA** (curto, escopo restrito) e do `X-Acting-Scope` (sintaxe exata do cabeçalho).
- Catálogo de **operações críticas** que exigem reautenticação (lista fechada, proposta no plano).
- Duração exata de sessão por perfil (12 h / 8 h como proposta) e política de senha (mín. 12 caracteres; verificação contra senhas vazadas — fonte/estratégia no plano).
- Estratégia de bloqueio progressivo e alertas de acesso (o que entra nesta leva vs. depois).
- Detalhe da senha "verificação contra senhas vazadas" (API externa? lista local? k-anonimato?).
- Ordem de fatias do plano (o responsável pediu **tudo de uma vez**; o plano pode fatiar em commits, não em entregas separadas).

## 7. Riscos

- **Migração de identidade in-place:** baixo (não há produção), mas exige teste de reversibilidade e que os testes existentes (login/me/organização) sejam atualizados junto — evitando janela de suíte vermelha.
- **MFA mal implementado** é o maior risco de segurança: sessão operacional vazando antes do segundo fator; segredo em claro; códigos em log. Mitigado pelos anti-objetivos e por testes dedicados.
- **Enumeração de contas** na recuperação: mitigada por resposta/tempo constantes + teste.
- **Octane e estado entre requisições:** o contexto de atuação não pode ser guardado em singleton mutável; o `ContextoAtuacao` é por request.
- **Escopo inflando:** as tecnologias "depois do MVP" (passkey, federação, WhatsApp) ficam fora, explicitamente.

## 8. Onde este desenho vive

`docs/superpowers/specs/2026-11-09-identidade-e-acesso-etapa-3-design.md`, versionado. Depois da aprovação, o próximo passo é o plano de implementação (skill `writing-plans`).