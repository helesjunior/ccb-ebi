# Design — Etapa 4 · Privacidade e auditoria (E05) · sub-etapa A1

**Data:** 09/11/2026 · **Base:** `main` @ `1778e8c` (docs v2.12) · **Épico:** E05 (RF-04, RF-16) · **Fase:** 1
**Abordagem aprovada:** A (E05 em duas sub-etapas). Este documento cobre a **A1**.

> **Pré-requisito de branch:** o desenvolvimento parte de `main` @ `1778e8c` (docs v2.12), que traz o [ADR-0029](../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md) e o padrão provisório do [ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md). O design *não* reconstrói essas regras: referencia-as.

## 1. Intenção e por quê

A E05 é a condição de entrada dos dados pessoais no sistema: todo dado de criança depende de consentimento e de trilha de auditoria. Esta sub-etapa entrega o **núcleo que fecha o CA-16** (consentimento com versão e evidência recuperáveis) e o que o [ADR-0029](../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md) referencia como base da finalidade registrada. A sub-etapa **A2** (dados sensíveis com chave própria + retenção configurável) vem em seguida, no mesmo ciclo de etapa.

O portão de dados reais continua na **etapa 8**: desenvolvimento e homologação só com **dados fictícios** ([ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md), plano §1.2).

## 2. Objetivos

1. **Consentimento versionado (RF-04, CA-16):** registrar termo (finalidade, versão, base legal, vigência, texto em rascunho) e a manifestação (aceite/recusa) com evidência, origem e data; permitir que um operador autorizado **recupere a versão do termo e a evidência**.
2. **Aceite no cadastro de responsável:** `POST /auth/register-guardian` passa a exigir o aceite do termo vigente e registra a manifestação na mesma transação.
3. **Direitos do titular (RF-16):** registrar solicitações (acesso, correção, revogação) com protocolo, responsável e prazo.
4. **Auditoria consultável (RF-16):** `GET /audit-events` por perfil autorizado (matriz: super_admin; adm_regional no regional; adm_setor no setor), com escopo territorial.
5. **Corrigir o `ator_id`:** as escritas auditadas passam a gravar quem agiu (hoje sempre `null`).

## 3. Não-objetivos (A1)

- **Registro de finalidade (`*`) operacional:** o mecanismo só tem onde ser exercitado quando existir o primeiro recurso sensível (participante/presença no E03/E04; laudo no E08). Fica como **dívida explícita** (ver §8). O ADR-0029 fala em "quando ele for implementado".
- **Exceção de acesso identificado acima da igreja** (pessoa nomeada, prazo ≤ 30 dias, aprovação do DPO): mecanismo do ADR-0029, sem consumidor no MVP (exportação identificável desligada). Dívida.
- **Dados sensíveis com chave própria + retenção/descarte/anonimização:** sub-etapa **A2**.
- **Fluxo completo do cadastro assistido (FL-07):** é E03. A1 deixa o **gancho** (manifestação com `origem = assistido` e evidência de termo em papel).
- **Textos finais dos termos:** ficam como **rascunho** até o jurídico.

## 4. Anti-objetivos

- **Não** presumir consentimento como base única (RF-04.4): a base legal é um dado do termo por finalidade, não um padrão fixo.
- **Não** expor o texto sensível/evidência em log nem em listagem ampla.
- **Não** alterar a trilha de auditoria existente (append-only, hash encadeado) — só consumi-la.
- **Não** reabrir a matriz de autorização: a consulta de auditoria usa os papéis que a matriz já define.

## 5. Abordagens consideradas

- **A — duas sub-etapas (escolhida).** A1 (este doc): consentimento + direitos do titular + auditoria consultável/ator. A2: chave própria + retenção. Entregável e revisável em pedaços; não deixa a dívida grande. Escolhida pelo produto.
- **B — E05 inteiro num PR.** Mais coeso, porém um PR grande e difícil de revisar.
- **C — só o núcleo e adiar o resto para o E08.** Rejeitada: o [ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md) (art. 11) trata "toda a base de presenças" como sensível, então o controle precisa existir já na E05.

Decisões internas da A1 (confirmadas com o produto):
- **Gancho de finalidade:** construir só auditoria consultável + `ator_id` agora; registro de finalidade quando houver recurso sensível — recomendação **(ii)**.
- **Aceite do termo:** obrigatório **no próprio** `register-guardian` — recomendação **(a)** — para o CA-16 ser demonstrável de ponta a ponta.

## 6. Desenho

### 6.1 Módulo e fronteiras
Novo módulo `App\Domain\Privacidade` (irmão de Identidade/Organizacao/Auditoria), com `README.md`, `Contracts/`, `Services/`, `Models/`, `Database/Factories/`. Respeita [ADR-0002](../../06-arquitetura/decisoes/ADR-0002-monolito-modular.md): `app/Http` não importa `Models`; entre módulos, só `Contracts` (o teste `ModularBoundariesTest` passa a cobrir o novo diretório automaticamente). Binding de Contract→Service em `DomainServiceProvider`.

### 6.2 Modelos
- **`termo_versao`**: `id` (ULID), `finalidade` (ex.: `cadastro`, `presenca`, `feedback`, `notificacoes`), `base_legal` (ex.: `consentimento`, `exercicio_regular`, `opt_in`), `versao`, `texto` (markdown, marcado **rascunho**), `texto_hash`, `vigente_em`, `encerrado_em` (nullable), timestamps. Único por (`finalidade`, `versao`). "Vigente" = `vigente_em <= hoje` e `encerrado_em` nulo.
- **`manifestacao_privacidade`**: `id` (ULID), `usuario_id` (nullable), `participante_id` (nullable), `termo_versao_id`, `decisao` (`aceito`/`recusado`/`retirado`), `origem` (`proprio`/`assistido`), `evidencia` (texto: ex. "aceite digital <ip/ua>" ou referência ao termo em papel), `ocorrido_em`, timestamps. Relação `termoVersao()`.
- **`solicitacao_titular`**: `id` (ULID), `solicitante_id`, `participante_id` (nullable), `tipo` (`acesso`/`correcao`/`revogacao`), `protocolo` (único, gerado), `responsavel_id` (nullable), `prazo` (date), `status` (`recebida`/`em_analise`/`atendida`/`recusada`), `descricao`, timestamps.

> Campo-a-campo completo segue o "modelo de detalhamento" do [dicionário de dados](../../03-dados/dicionario-de-dados.md); a migration é a fonte final.

### 6.3 Contratos e serviços (módulo Privacidade)
- `GerirTermos`: criar/publicar versão de termo (rascunho→vigente), listar vigentes por finalidade.
- `RegistrarConsentimento`: `registrar(usuario, termoVersaoId, decisao, origem, evidencia)` → manifestação; `retirar(...)` → nova manifestação `retirado`.
- `ConsultarConsentimentos`: recuperar, para operador autorizado, a manifestação + a versão do termo (texto_hash + versão) → **CA-16**.
- `SolicitarDireitoTitular`: abrir solicitação com protocolo e triagem inicial.

Os contratos recebem `Authenticatable`/ids (não Models) para `app/Http` permanecer model-free.

### 6.4 Endpoints (contract-first: entram no OpenAPI antes do código)
- `GET /privacy/terms` — termos vigentes (por finalidade). Autenticado.
- `GET /privacy/consents` — manifestações do próprio usuário (o titular lê o seu). Autenticado.
- `POST /privacy/consents` — registrar aceite/recusa/retirada do próprio usuário. Autenticado.
- `POST /privacy/requests` — abrir solicitação de direito do titular. Autenticado.
- `GET /privacy/consents/{id}` — **recuperação pelo operador autorizado** (CA-16): devolve a manifestação + a versão do termo (versão + `texto_hash`) + a evidência. Sob `can:consentimento.ler` (§6.6-bis).
- `GET /audit-events` — consulta da trilha, sob `can:auditoria.ler` (papel + escopo, §6.6).
- **Alterado:** `POST /auth/register-guardian` — corpo passa a exigir aceite (`termo_versao_id` + `aceite: true`).

Catálogo `docs/04-api/endpoints.md` marcado `no OpenAPI`; `check-endpoints.py` valida.

**Autorização dos endpoints do titular:** `GET`/`POST /privacy/consents` e `POST /privacy/requests` são o titular sobre os **próprios** dados — não passam por `can:` nem pela matriz de operações (que governa papéis operacionais). O serviço resolve o escopo por "sou eu" (`usuario_id` = ator), e `participante_id` só é aceito quando o ator é responsável **daquele** participante (gancho do cadastro assistido; a relação responsável–participante é E03 — enquanto não existir, o serviço aceita apenas o próprio usuário e rejeita `participante_id`). A leitura por operador de **outrem** é o `GET /privacy/consents/{id}` acima, esse sim sob a matriz (§6.6-bis).

### 6.5 Aceite no cadastro de responsável (CA-16 ponta a ponta)
`RegistrarRequest` ganha `termo_versao_id` (exists) e `aceite` (accepted). `GerenciadorConta::registrar` recebe o termo vigente da finalidade `cadastro`, cria o usuário/vínculo e a **manifestação `aceito` com `origem = proprio` e `evidencia`** (hash do termo + ip/ua) **na mesma transação**. Sem termo vigente → 422 `termo_indisponivel`. O portal (`frontend`) apresenta o termo e o checkbox.

### 6.6 Auditoria: consulta + ator
- **Consulta:** `AuditoriaController@index` → `ConsultarAuditoria` (módulo Auditoria) com paginação por cursor e filtros (período, ator, recurso). Autorizado por `can:auditoria.ler` via `AuditoriaPolicy` → `AutorizarOperacao`, com novo `Operacoes::AUDITORIA_LER` na matriz: `super_admin` (geral), `adm_regional` (escopo regional), `adm_setor` (escopo setor). Demais papéis: negado.
- **Escopo territorial da trilha sem alterar o schema:** a tabela `evento_auditoria` não tem nó territorial estruturado; tem `escopo` textual no formato `tipo:id` e `recurso`. A consulta **filtra pelo `escopo` textual**, revalidando o nó contra a hierarquia via `ResolverEscopo`/`ConsultarHierarquia` (o mesmo núcleo ABAC). Regra de visibilidade: eventos com `escopo` territorial dentro da subárvore do vínculo são visíveis; eventos **sem** escopo territorial (ou fora da subárvore) só para `super_admin`. Sem migração e sem tocar o hash encadeado (§4).
- **`ator_id`:** os serviços que auditam (`GerenciadorUsuario`, `GerenciadorVinculo`, `ServicoTransferencia`) passam a receber/propagar o `usuario_id` do atuante a partir do `ContextoAtuacao`/request; o registro de auditoria grava `ator_id`. Teste cobre que a escrita auditada tem `ator_id` preenchido.

### 6.6-bis Operação `consentimento.ler` (recuperação CA-16 por operador)
Novo `Operacoes::CONSENTIMENTO_LER` na matriz, para o `GET /privacy/consents/{id}` (leitura de manifestação de outrem). Papéis com acesso: `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja` — alinhado à linha "Usuários e vínculos de acesso" da [matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) e ao escopo territorial. Aplicada via `ConsentimentoPolicy` → `AutorizarOperacao`. Registrar/retirar o **próprio** consentimento **não** usa operação da matriz (§6.4).

### 6.6-ter Evidência do aceite no cadastro (seam Http↔Domain)
`app/Http` extrai `ip`/`user-agent` e monta um value object `EvidenciaAceite` (ex.: `{ ip, userAgent, hashTermo }`), passando-o ao contrato `RegistrarConta::registrar(...)`. Assim o serviço de domínio continua model-free e não conhece a request; a evidência fica registrada na mesma transação do cadastro (§6.5). (**meu call**, §7.)

### 6.7 Portal (React)
Telas mínimas e acessíveis: (a) aceite do termo no fluxo de cadastro do responsável; (b) "Privacidade" — termo vigente, consentimento atual, botão de retirada; (c) formulário de solicitação (acesso/correção/revogação); (d) "Auditoria" — listagem para perfis autorizados. Sem runner de testes no frontend: validado por `oxlint` + `tsc -b` + `build` (dívida registrada em [ADR-0022](../../06-arquitetura/decisoes/ADR-0022-identidade-e-acesso.md)).

## 7. Restrições e decisões do construtor
- Textos dos termos e o e-mail de privacidade nascem **rascunho/configuráveis** (**meu call**, alinhado ao ADR-0019 item 8).
- Base legal por finalidade vem do ADR-0019 item 1, **não** de um enum fechado no código além do necessário (**meu call**: coluna texto, não enum, para o jurídico poder ajustar).
- Protocolo da solicitação: ULID curto legível; **meu call** (sem requisito específico).
- Paginação de `/audit-events` por cursor, seguindo `PaginacaoCursor`.
- **Evidência do aceite** montada em `app/Http` e passada por um value object ao contrato (§6.6-ter) — **meu call**.
- **Escopo de `/audit-events`** via parsing do `escopo` textual + revalidação na hierarquia, sem migração (§6.6) — **meu call**.
- **Titular × operador:** endpoints do titular são "sobre si" (sem matriz); leitura de outrem é `GET /privacy/consents/{id}` sob `consentimento.ler` (§6.4/§6.6-bis) — **meu call**.

## 8. Dívidas registradas (A1)
1. **Registro de finalidade (`*`)** e **exceção de acesso identificado** (ADR-0029) — aguardam o primeiro recurso sensível (E03/E04/E08).
2. **`ator_id`** em eventos fora dos 3 serviços já auditados (demais módulos futuros).
3. **Cadastro assistido (FL-07)** completo é E03; A1 entrega o gancho.
4. **Textos finais** dos termos dependem do jurídico/DPO.
5. **Testes de frontend** continuam ausentes.

## 9. Critérios de aceite da A1
- **CA-16:** um operador autorizado recupera, via `GET /privacy/consents/{id}`, a **versão do termo** e a **evidência** de uma manifestação (teste automatizado de contrato); acesso de operador fora do escopo → negado.
- Registrar o responsável **exige** aceite do termo vigente; sem aceite → 422; a manifestação é gravada na mesma transação (teste).
- `GET /audit-events` respeita papel + escopo; acesso horizontal (outra unidade do mesmo nível) negado; evento sem escopo territorial visível só a `super_admin` (teste).
- **Toda escrita auditada** grava `ator_id` não nulo (teste).
- Regras de negócio cobertas por teste; Pint, PHPStan e `check-endpoints` verdes.

## 10. Ordem de construção (a detalhar no plano)
1. Módulo `Privacidade` (esqueleto + README + binding).
2. `termo_versao` + seeder de termos rascunho + `GerirTermos` + `GET /privacy/terms`.
3. `manifestacao_privacidade` + `RegistrarConsentimento`/`ConsultarConsentimentos` + `GET/POST /privacy/consents` + `GET /privacy/consents/{id}` (CA-16) + `Operacoes::CONSENTIMENTO_LER`/`ConsentimentoPolicy`.
4. Aceite obrigatório no `register-guardian` (CA-16 ponta a ponta) + portal.
5. `solicitacao_titular` + `POST /privacy/requests`.
6. Auditoria: `GET /audit-events` + `Operacoes::AUDITORIA_LER` + `AuditoriaPolicy` + `ator_id`.
7. Portal: telas de privacidade e auditoria.
8. Docs: OpenAPI, endpoints.md, CHANGELOG, ADR da etapa, rastreabilidade.