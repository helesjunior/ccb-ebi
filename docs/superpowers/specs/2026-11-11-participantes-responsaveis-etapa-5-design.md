# Design — Etapa 5: Participantes e responsáveis (E03)

**Data:** 11/11/2026 · **Base:** `main` @ `b9dfd86` (fundação de UI mergeada, PR #28) · **Épico:** E03 (RF-02, RF-03) · **Fase:** 1
**Método de execução:** subagentes por task + review fresco por task + review whole-branch no fim (mesmo das etapas anteriores).

## 1. Intenção e por quê

A Etapa 5 introduz a **entidade central do sistema**: o **participante**. Hoje o repositório tem
hierarquia (`Organizacao`), identidade (`Identidade`), privacidade (`Privacidade`) e auditoria
(`Auditoria`) — mas **não existe** módulo, tabela nem rota de participante. Nada de presença (E04),
relatórios (E06) ou escalas (E07) existe sem ele.

Esta etapa cria o cadastro único do participante (RF-02), os responsáveis e seus vínculos (RF-03),
o cadastro assistido pela coordenadora (FL-07/ADR-0015), a elegibilidade etária com a exceção
inclusiva (RN-13 a RN-16, ADR-0012/0026) e a deduplicação/mesclagem auditada (ADR-0007/0027).

**Decisão do responsável do produto:** atacar a **etapa inteira** (não sub-dividir).
**Decisão sobre storage (opção C):** entregar o **pipeline completo e reutilizável** de upload de
arquivo (quarentena → validação de tipo real → ClamAV → bucket cifrado → URL assinada) já nesta
etapa, aplicado à **evidência do consentimento do cadastro assistido**. A biblioteca de PDFs (E08)
reusa esse pipeline depois; nesta etapa ele não é aplicado a ela.

## 2. Objetivos

1. **Cadastro único do participante** (RF-02): matrícula nacional imutável e não previsível,
   dados mínimos, situação cadastral, igreja/EBI de referência (opcional), histórico.
2. **Matrícula estável entre igrejas** (CA-01): reusar a mesma matrícula, sem criar outro registro.
3. **Responsáveis e vínculos** (RF-03 / CA-04): mais de um responsável; fluxo de solicitação,
   validação, contestação e revogação; `usuario_id` **opcional** (responsável sem conta).
4. **Cadastro assistido** (FL-07 / ADR-0015 / CA-21): coordenadora cadastra presencialmente; cadastro
   marcado `assistido`; evidência de consentimento **exigida** e armazenada pelo pipeline de storage.
5. **Elegibilidade etária + exceção inclusiva** (RN-13..RN-16 / ADR-0012/0026 / CA-17, CA-19, CA-22):
   cálculo no fuso da igreja, bloqueio <3 anos, exceção decidida pela coordenadora com o responsável,
   com todos os estados.
6. **Deduplicação e mesclagem** (ADR-0007/0027 / CA-27): detecção de possíveis duplicidades,
   revisão humana, mesclagem auditada e reversível, com escopo por EBI.
7. **Pipeline de storage reutilizável** (ADR-0005): upload com verificação antivírus e bucket próprio.

## 3. Não-objetivos

- **Presença e sessões** (E04) — esta etapa entrega o serviço de **elegibilidade** que a E04
  consumirá; não registra presença.
- **Relatórios** (E06) e **escalas** (E07).
- **Biblioteca de PDFs** (E08) — o pipeline é reutilizável, mas não é aplicado a ela aqui.
- **Notificação de 30 dias antes do 12º aniversário** (RF-15) — proposta complementar; fica para a
  etapa de notificações.
- **App nativo** (E11) e offline (E13).
- **Passkeys** e qualquer item fora de RF-02/RF-03.
- **Laravel Boost** (decisão do responsável: não instalar agora).

## 4. Anti-objetivos

- **Não** fundir cadastros automaticamente e de forma irreversível (ADR-0007).
- **Não** expor dado sensível (laudo/diagnóstico) fora do módulo segregado, nem em relatórios.
- **Não** permitir que conhecer a matrícula baste para acessar dados do participante (CA-04).
- **Não** quebrar as fronteiras modulares (ADR-0002) — nada de um módulo ler tabela de outro.
- **Não** deixar a coordenadora mesclar fora dos EBIs onde atua (ADR-0027).
- **Não** guardar cópia do documento de comprovação do responsável fora de guarda judicial
  (ADR-0018) — registra-se a conferência, não o arquivo.

## 5. Abordagens consideradas

- **A (escolhida): módulo único `Participantes`, fatia vertical completa.** Entrega tudo desta etapa
  de ponta a ponta num módulo coeso. Recomendada.
- **B: dividir em sub-etapas (A1/A2).** Menos risco por ciclo, mas o responsável pediu a etapa
  inteira. Rejeitada.
- **C (escolhida para o storage): pipeline completo, aplicado só à evidência.** Prova o pipeline de
  storage real agora, sem arrastar a biblioteca de PDFs (E08). A E08 reusa.

## 6. Desenho

### 6.1 Módulo e fronteiras
Novo módulo `App\Domain\Participantes` (padrão dos existentes: `Models/`, `Contracts/`, `Services/`,
`Database/Factories/`, `Exceptions/`). Expõe **contratos** (interfaces) para os demais módulos —
nenhum módulo lê as tabelas diretamente (ADR-0002; `ModularBoundariesTest` cobre).
O módulo `Participantes` **consome** contratos de `Organizacao` (EBI/igreja), `Privacidade`
(`RegistrarConsentimento`) e `Auditoria` (`RegistrarEvento`). O dado sensível do participante
(`dado_sensivel_participante`) **não** fica em `Privacidade`: vive no módulo `App\Domain\DadosSensiveis`
(contratos `ArmazenarDadoSensivel`/`LerDadoSensivel`) — `Participantes` o consome por esse contrato,
nunca pela tabela. O pipeline de storage fica em `App\Support` (infra transversal, como
`ProblemException`/`EvidenciaAceite` hoje), para a E08 reusar sem depender de `Participantes`.

### 6.2 Dados (migrations novas)
- **`participante`** — `id` (ULID), `matricula` (unique), `nome`, `nome_social` (nullable),
  `data_nascimento`, `status`, `igreja_referencia_id` (nullable), `origem_cadastro`
  (`responsavel`|`assistido`), `cadastrado_por` (nullable), `evidencia_consentimento_id` (nullable),
  timestamps. Índices em `matricula`, `nome`, `igreja_referencia_id`.
- **`responsavel_participante`** — `id`, `usuario_id` (nullable), `nome`, `telefone` (nullable),
  `participante_id`, `tipo_relacao`, `permissoes`, `restricoes` (nullable), `status`, `validacao`
  (`pendente`|`aprovada`|`aguardando_documento`|`recusada`), `conferido_em` (nullable),
  `conferido_por` (nullable), `inicio`, `fim` (nullable). *Situação corrente; o gate de acesso é
  `validacao`.*
- **`solicitacao_vinculo`** — `id`, `usuario_id`, `participante_id`, `responsavel_participante_id`
  (nullable), `tipo` (`solicitacao`|`contestacao`|`revogacao`), `status`, `decidido_por` (nullable),
  `decidido_em` (nullable), `motivo` (nullable), timestamps. *Log append-only.*
- **`excecao_etaria`** — `id`, `participante_id`, `tipo_necessidade`, `apoios_necessarios`,
  `justificativa_funcional`, `status` (`solicitada`|`aprovada`|`recusada`|`suspensa`|`revogada`|
  `expirada`), `solicitado_por` (nullable), `decidido_por` (nullable),
  `responsavel_concordante_id` (nullable — aponta para `responsavel_participante`, pois o responsável
  pode não ter conta; nulo em casos de guarda judicial), `decidido_em`, `vigente_ate` (nullable),
  `revisao_prevista_em` (nullable), `revogado_em` (nullable), `motivo` (nullable).
- **`arquivo`** (pipeline de storage) — `id`, `chave_storage`, `mime`, `tamanho`, `sha256`,
  `status_antivirus` (`pendente`|`limpo`|`infectado`|`invalido`), `bucket`, `cifrado` (bool),
  `enviado_por` (nullable), timestamps.

### 6.3 Matrícula (RF-02.1 / CA-25 / CA-01)
- 8 caracteres aleatórios em **base32 Crockford** (40 bits, sem letras ambíguas) + 1 dígito
  verificador; nunca derivados de sequência, data ou dado pessoal. Unicidade garantida por índice
  `unique` + retry em colisão. Serviço `GeradorMatricula` (isolado e testável com seeded RNG).

### 6.4 Elegibilidade etária (RN-13..RN-16)
Serviço único `AvaliadorElegibilidade` (a E04 consome via contrato `AvaliarElegibilidade`):
- Calcula a idade na **data local da sessão, no fuso da igreja**; nascidos em 29/02 completam ano
  em 01/03 nos anos não bissextos (RN-16).
- `< 3` anos → **bloqueia** sempre (CA-19). `>= 3` e `< 12` → permite. `>= 12` sem exceção vigente
  → bloqueia. Exceção `aprovada` e dentro de `vigente_ate` → permite (CA-17, CA-22).
- Exceção `suspensa`/`revogada`/`expirada` impede novas presenças; histórico preservado (RN-15).
- O serviço recebe a data da sessão e os dados mínimos do participante/exceção — **não** depende de
  sessão nem de presença (que não existem ainda).

### 6.5 Cadastro assistido + evidência (FL-07 / CA-21 / ADR-0005)
- Rota de cadastro assistido no contexto do EBI da coordenadora: cria participante (`origem_cadastro
  = assistido`) + responsável sem conta (`usuario_id = null`).
- **Consentimento sem conta (call do builder):** o contrato `RegistrarConsentimento` passa a aceitar
  `$usuarioId` **nulo** e uma evidência com **referência de arquivo** (`origemArquivo`), além de
  IP/UA. Não muda o schema de `manifestacao_privacidade` (`usuario_id` já é nullable; `participante_id`
  já existe e a FK entra nesta etapa). A evidência textual passa a mencionar o arquivo, ex.:
  `aceite assistido arquivo=<id> conferido_por=<usuario>`.
- **Evidência obrigatória:** um `arquivo` no pipeline. Fluxo: a API grava em bucket `quarentena` e
  enfileira um job; o worker valida o **tipo real** (assinatura de PDF/imagem, não extensão), envia
  ao `clamd` (3310), e em caso de aprovação move para o bucket `laudos` **cifrado** com `laudos_key`
  ([ADR-0006]); arquivo infectado/inválido é apagado e o evento vai para a auditoria. O
  `participante.evidencia_consentimento_id` aponta para o `arquivo`. Cadastros assistidos ficam
  **marcados para auditoria** (ADR-0015).
- **Downloads** do arquivo só por **URL assinada** de curta duração, após checagem de autorização.
- Fallback de teste: em `testing`, o disco pode ser `fake`/local; o teste do job usa o `Storage` fake
  e um dublê do cliente clamd (não sobe um ClamAV real na suíte).

### 6.6 Vínculos (RF-03 / ADR-0018)
- **Duas tabelas com papéis distintos (call do builder):** `responsavel_participante` é a **situação
  corrente** do vínculo (uma linha por responsável×participante, mutável: `validacao`, `permissoes`,
  `restricoes`, `inicio`, `fim`); `solicitacao_vinculo` é o **log append-only** de pedidos
  (`tipo = solicitacao|contestacao|revogacao`, com decisor, data, motivo). O **gate de acesso** é
  `responsavel_participante.validacao = aprovada` (CA-04); o log só registra a história, nunca gateia.
- Fluxo `solicitado → aguardando_documento → aprovado|recusada`; contestação e revogação com motivo.
- A coordenadora valida; **só em caso de dúvida** pede comprovação → `aguardando_documento`. Registra
  **que conferiu** (tipo, data, quem), **não** guarda cópia — exceto guarda judicial (ADR-0018).
- Conhecer a matrícula não autoriza acesso (CA-04): o acesso a dados do participante exige vínculo
  `aprovada`; o responsável vê **apenas** seus dependentes.
- Mais de um responsável por participante, com permissões e restrições individuais.

### 6.7 Deduplicação e mesclagem (RF-02.5 / ADR-0007/0027 / CA-27)
- Detecção de possíveis duplicidades por combinação de atributos (nome normalizado + data de
  nascimento, etc.), **sem revelar cadastros de terceiros** ao responsável; encaminha para revisão.
- Mesclagem **manual**, auditada e **reversível** (guarda o estado anterior para desfazer). Sem fusão
  automática.
- **Escopo nesta etapa (call do builder):** como `presenca` só existe na E04, o escopo da coordenadora
  cai apenas sobre a **igreja de referência** do participante ("tem igreja de referência em um EBI onde
  ela atua"). Quando a E04 chegar, a regra soma a presença. Fora disso → coordenadora chefe. Dívida
  registrada (§8).

### 6.8 Dado sensível
Laudo/diagnóstico (quando indispensável) vai ao `dado_sensivel_participante` via contrato do módulo
`DadosSensiveis` (contrato `ArmazenarDadoSensivel`, nunca a tabela), com acesso restrito e **nunca**
em relatórios (CA-06). A leitura é auditada (E05 A2) e usa `LerDadoSensivel`.

### 6.9 Autorização (matriz de autorização)
A matriz de autorização marca, para o **Responsável**, `L (dependentes)` e `R (dependentes)`. Isso é
**propriedade** (o ator só age sobre os próprios dependentes), não escopo territorial — o
`AutorizadorOperacao`/`ResolverEscopo` existentes só expressam subárvore de organização. Por isso:

- Novas operações no catálogo (`Operacoes`) com escopo **territorial** (RBAC + subárvore, como as
  demais): `participante.ler`, `participante.cadastrar`, `participante.corrigir`,
  `participante.mesclar`, `vinculo.gerenciar`, `excecao.ler`, `excecao.decidir`, `evidencia.ler`.
- **Caminho do responsável (call do builder):** uma verificação de **propriedade** explícita no módulo
  `Participantes` — o ator com papel `responsavel` só lê/escreve o participante se existir
  `responsavel_participante` com `validacao = aprovada` e `usuario_id = ator`. Exposta como operação
  própria (`participante.ler_dependente` / `participante.cadastrar_dependente`) e testada com acesso
  horizontal (dependente de outro) negado. **Não** se inventa ABAC territorial para o responsável.
- **`*` (finalidade registrada) — resolvido com uma distinção que o doc revela.** O ADR-0030 (§8,
  dívida 1) diz que o registro de finalidade e a exceção do ADR-0029 "aguardam um recurso sensível
  para fazer sentido (e a definição de DPO/CCB)"; o ADR-0029 é **proposto e aguarda CCB/DPO**. Ou
  seja: a Etapa 5 é o gatilho (primeiro recurso sensível), mas o **mecanismo completo** de exceção
  (pessoa nomeada + finalidade + prazo ≤30 dias + aprovação do DPO) **não pode** ser entregue agora —
  depende de aprovação externa. O que esta etapa faz, sem depender disso:
  1. **Leitura sensível/identificável exige finalidade declarada**, com a declaração **registrada na
     auditoria** (o operador informa a finalidade; o evento guarda quem, o quê e a finalidade). Isso é
     o núcleo do `*` que não depende do DPO.
  2. **A exceção do ADR-0029** (nomeado, prazo, aprovação do DPO) fica como **dívida explícita**, para
     quando CCB/DPO confirmarem (§8).
  - **Nuance importante:** "finalidade" aqui **não** é a `finalidade` do termo de consentimento
    (`termo_versao.finalidade`, ex. `cadastro`). São conceitos distintos: uma é o propósito do termo
    de privacidade; a outra é a finalidade de uma **leitura de dado**. Não reusar a primeira pela
    segunda.

### 6.10 Contrato e telas
- **Contract-first:** todos os endpoints descritos no `docs/04-api/openapi.yaml` **antes** do código,
  com `no OpenAPI` em `endpoints.md`; `scripts/check-endpoints.py` confere (48 operações hoje → mais
  as novas). Regenerar `frontend/src/api/schema.d.ts`.
- **Telas** (frontend, seguindo a fundação de UI/shadcn): cadastro de participante (responsável),
  meus dependentes, cadastro assistido (coordenadora), revisão de duplicidade/mesclagem, solicitação
  e decisão de vínculo, exceção inclusiva. A casca e as telas novas usam componentes shadcn.

## 7. Restrições

- **Contract-first** e catálogo alinhado (`check-endpoints.py` verde).
- **Toda escrita sensível gera auditoria**; nenhum dado pessoal em log.
- **Fronteiras modulares** (ADR-0002) — `ModularBoundariesTest` deve continuar verde.
- **Backend PHP** via `docker compose run --rm app <cmd>`; **frontend** com Node do host.
- Testes: Pest (feature + unit); autorização testada (quem pode / quem não pode), incluindo
  escopo horizontal e vertical.
- Sem dados reais (portão da etapa 8); seeder com hierarquia e casos-limite de idade (véspera e dia
  do 12º aniversário, <3 anos, exceção vigente e expirada).
- Gotcha de ambiente: containers `app`/`horizon`/`scheduler` não recarregam código — reiniciar após
  mudanças de backend.

## 8. Dívidas registradas (desta etapa)

1. **Exceção do ADR-0029** (acesso identificado acima da igreja: pessoa nomeada + prazo ≤30 dias +
   aprovação do DPO) — proposto e **aguarda CCB/DPO**. Esta etapa entrega a finalidade declarada e
   auditada nas leituras; o mecanismo completo fica para quando houver aprovação.
2. Registro de finalidade (`*`) nas demais leituras fora de participante.
3. Notificação de 30 dias antes do 12º aniversário (RF-15).
4. Anexo de laudo da exceção inclusiva (FL-06) — o pipeline existe, mas o fluxo de anexo pelo
   responsável fica com o jurídico/DPO (D-04).
5. Concordância do próprio participante adulto (D-04, jurídico).
6. Reuso do pipeline de storage pela biblioteca (E08) — por desenho, fora desta etapa.
7. Laravel Boost (setup de dev) — não instalado.
8. **Escopo de mesclagem por presença:** nesta etapa só a igreja de referência gateia (a `presenca` é
   da E04). Ao chegar a E04, somar a presença ao escopo (ADR-0027).
9. **`dicionario-de-dados.md` e `estados.md`** ainda não descrevem `participante`,
   `responsavel_participante`, `solicitacao_vinculo`, `excecao_etaria` e `arquivo` — atualizar nesta
   etapa (rastreabilidade).

## 8.1 Resoluções do builder check (registro)

- **Q1 consentimento assistido:** contrato `RegistrarConsentimento` aceita `$usuarioId` nulo e
  evidência com referência de arquivo; sem mudança de schema em `manifestacao_privacidade`.
- **Q2 vínculo:** `responsavel_participante` (situação corrente) vs `solicitacao_vinculo`
  (log append-only); o gate é `validacao = aprovada`.
- **Q3 autorização do responsável:** propriedade explícita (dependentes), não ABAC territorial.
- **Q4 `responsavel_concordante_id`:** aponta para `responsavel_participante`, nullable.
- **Q6 escopo CA-27:** só `igreja_referencia_id` nesta etapa (dívida 8 acima).
- **`*` (finalidade registrada):** entregue como finalidade declarada + auditada nas leituras sensíveis
  de participante; a exceção do ADR-0029 fica como dívida (aprovação CCB/DPO).
- **Achado:** `dado_sensivel_participante` vive em `DadosSensiveis`, não em `Privacidade`.
- **Achado:** "finalidade" do termo (consentimento) ≠ "finalidade" de leitura (matriz `*`).

## 9. Critérios de aceite

- CA-01, CA-04, CA-06, CA-17, CA-19, CA-21, CA-22, CA-25 e CA-27 passam (testes automatizados).
- Endpoints no OpenAPI e catálogo alinhado (`check-endpoints.py` verde); testes de contrato passando.
- Testes de autorização cobrindo cada operação nova (quem pode / quem não pode), com escopo.
- Escrita sensível (cadastro assistido, mesclagem, exceção, vínculo) gera registro de auditoria.
- Leitura sensível/identificável acima da igreja exige **finalidade declarada**, registrada na
  auditoria (núcleo do `*` da matriz); a exceção do ADR-0029 fica registrada como dívida.
- Mesclagem reversível comprovada por teste; sem fusão automática.
- Elegibilidade coberta por testes de tabela com datas-limite (29/02, véspera/dia do 12º, <3).
- Evidência do cadastro assistido: pipeline de storage provado (quarentena → validação → ClamAV →
  bucket cifrado → URL assinada), com dublê na suíte.
- Fronteiras modulares verdes; nenhum dado pessoal em log; sem dados reais.
- Telas funcionais e acessíveis (teclado, contraste), usáveis no celular.

## 10. Ordem de construção (detalhada no plano)

1. Migrations + models + factories do módulo `Participantes` (sem rotas ainda).
2. Matrícula (gerador) + cadastro do participante pelo responsável (contrato + serviço + rota).
3. Vínculos de responsável (solicitação/validação/contestação/revogação) + autorização.
4. Elegibilidade etária + exceção inclusiva (serviço + rotas + testes de tabela).
5. Deduplicação + mesclagem auditada e reversível + escopo por EBI.
6. Cadastro assistido + pipeline de storage (evidência cifrada) + job na fila.
7. OpenAPI + catálogo + regenerar cliente; autorização consolidada.
8. Telas (frontend shadcn) — cadastro, dependentes, assistido, mesclagem, vínculos, exceção.
9. Seeder com casos-limite + docs (CHANGELOG, ADR novo, rastreabilidade) + verificação manual.