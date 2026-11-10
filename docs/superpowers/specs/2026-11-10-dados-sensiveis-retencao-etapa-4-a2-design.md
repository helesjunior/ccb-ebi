# Design — Etapa 4 · Privacidade e auditoria (E05) · sub-etapa A2

**Data:** 10/11/2026 · **Base:** `main` @ `b9c208e` (A1 mergeada, PR #26) · **Épico:** E05 (RF-04, RF-16) · **Fase:** 1
**Abordagem:** A (E05 em duas sub-etapas). A1 entregue e mergeada; este documento cobre a **A2**.
**Método de execução:** subagentes por task (escolha do produto), review fresco por task + review whole-branch no fim.

> Deriva do [design da A1](2026-11-09-privacidade-auditoria-etapa-4-design.md) §3 (não-objetivos) e §8 (dívidas), e das ADRs [0030](../../06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md) (dívida "A2 — dados sensíveis com chave própria + retenção"), [0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md) (prazos de retenção provisórios), [0006](../../06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md) (chave separada da `APP_KEY`, só o módulo de dados sensíveis a lê) e [0002](../../06-arquitetura/decisoes/ADR-0002-monolito-modular.md) (fronteiras). A A2 **não** reconstrói essas regras: referencia-as.

## 1. Intenção e por quê

A A1 fechou o CA-16 (consentimento versionado + evidência recuperável) e tornou a trilha de auditoria consultável. Ficou de fora, explicitamente, o que o [plano §Etapa 4](../../07-produto/plano-de-desenvolvimento.md) exige para a etapa estar completa:

- **Módulo de dados sensíveis com chave própria, separada da `APP_KEY`** ([ADR-0006](../../06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md)).
- **Prazos de retenção como configuração**, com rotina de descarte e anonimização **desligada até o DPO aprovar** os prazos ([RNF-07](../../01-requisitos/nao-funcionais.md)).
- **Toda leitura de dado sensível gera registro de auditoria** — a condição de "pronto" da Etapa 4.

O portão de dados reais continua na **etapa 8**: desenvolvimento e homologação só com **dados fictícios** ([ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md)).

## 2. Objetivos

1. **Módulo segregado de dados sensíveis** (`App\Domain\DadosSensiveis`), com **chave própria** lida de `SENSITIVE_DATA_KEY`/`SENSITIVE_DATA_KEY_FILE`, nunca da `APP_KEY` e nunca com fallback implícito ([ADR-0006](../../06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md)).
2. **Armazenamento cifrado**: entidade `dado_sensivel_participante` com `conteudo_cifrado` (ou `referencia_segura` para arquivo em bucket próprio), `finalidade`, `base_legal`, `controle_acesso` e `prazo_retencao` ([dicionário](../../03-dados/dicionario-de-dados.md), modelo conceitual).
3. **Leitura auditada**: obter um dado sensível (descriptografado) **sempre** grava um evento de auditoria com `ator_id` e a finalidade — a "pronto quando" da Etapa 4.
4. **Motor de retenção configurável**: prazos por tipo de dado em configuração, com **rotina de descarte/anonimização desligada por padrão** (`PRIVACIDADE_RETENCAO_ATIVA=false`) e acionável por comando artisan; o motor é **idempotente** e **audita** cada ação de descarte/anonimização.

## 3. Não-objetivos (A2)

- **Consumidor real de dado sensível:** a relação participante existe no E03 e o laudo no E08. A A2 entrega o **mecanismo** e o prova com um caso sintético (como a A1 fez com o gancho de finalidade). O `participante_id` fica como coluna sem FK real, exatamente como em `manifestacao_privacidade` (A1).
- **Texto/estrutura de laudo e exceção do [ADR-0029](../../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md):** continuam sem consumidor (dívida herdada da A1).
- **Encarregado (DPO)/canal do titular operacional, incidentes ([ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md) itens 7–8):** não entram.
- **Bucket próprio de laudos (MinIO/S3) e antivírus:** são [E08/ADR-0005](../../06-arquitetura/decisoes/ADR-0005-armazenamento-e-antivirus.md); a A2 só prevê a coluna `referencia_segura`, sem ligar o storage.
- **Prazos aprovados pelo DPO:** os da A2 são os **provisórios** do [ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md); a rotina nasce desligada.
- **Frontend novo:** a A2 é backend-only (não há tela de dado sensível no portal até existir o recurso real). Sem mudança de contrato público de API.
- **Rework da consulta de auditoria (dívida da A1):** fica para o gatilho de volume já registrado.

## 4. Anti-objetivos

- **Não** usar a `APP_KEY` como chave de dados sensíveis, nem cair nela silenciosamente se a chave própria faltar (falha explícita).
- **Não** logar conteúdo sensível (nem cifrado nem em claro) — só metadados minimizados (`tipo`, `finalidade`, ids).
- **Não** ligar a retenção por padrão nem apagar/anonimizar em produção sem config+comando explícitos.
- **Não** tocar a trilha de auditoria (append-only, hash encadeado) — só consumi-la via `RegistrarEvento`.
- **Não** abrir endpoint público para dado sensível: a leitura é por serviço/contrato, com auditoria; quando houver tela, ela entra por uma etapa com contrato no OpenAPI.

## 5. Abordagens consideradas

- **A — módulo segregado + leitura auditada + motor de retenção desligado (escolhida).** Espelha a A1 (mecanismo completo, dívida explícita do consumidor), respeita [ADR-0006](../../06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md) (só o módulo lê a chave) e entrega a "pronto quando" da Etapa 4 (leitura sensível auditada). Revisável em pedaços.
- **B — só o cofre de chaves (envelope encryption) agora, sem entidade.** Menor, mas não fecha a "pronto quando" da etapa (não há leitura sensível para auditar) nem prova o motor de retenção. Rejeitada.
- **C — E05 + E08 (laudo) juntos.** Coeso para o domínio, porém grande, sem consumidor até o E08 e misturaria etapas. Rejeitada.

### Decisões internas ("meu call", confirmáveis na revisão)
- **Chave:** variável `SENSITIVE_DATA_KEY` (base64 de 32 bytes), com variante `SENSITIVE_DATA_KEY_FILE` (lida pelo `EnvReadsSecretFiles`/padrão twelve-factor já usado no projeto). Cifra com AES-256-GCM via `Illuminate\Encryption\Encrypter` (`openssl`), **instanciado só no módulo**. Ausente/inválida → exceção na **primeira leitura/escrita**, nunca fallback para `APP_KEY`.
- **Colunas de cripto:** `conteudo_cifrado` (texto) **exclusivo** com `referencia_segura` (texto) — pelo menos um preenchido; o par é validado no serviço.
- **Controle de acesso:** `controle_acesso` textual (quem/que papel pode ler), **respeitado pelo serviço** que resolve a leitura (gancho; sem endpoint na A2).
- **Leitura auditada:** o serviço `LerDadoSensivel` grava evento `acao=dado_sensivel.ler`, `recurso=dado_sensivel_participante`, `escopo` derivado da finalidade/contexto do chamador quando houver, `metadados_minimizados={tipo, finalidade}`, `ator_id` obrigatório. Leitura sem ator → erro (não há leitura anônima de dado sensível).
- **Retenção:** config `config/privacidade.php` com `retencao.ativa` (bool, default `false`) e `retencao.prazos` (mapa tipo→prazo em meses, valores do ADR-0019). Comando `privacidade:expurgar` roda o motor; **sem** `--force` quando `ativa=false` ele apenas **relata** (dry-run) e sai com sucesso, sem apagar nada.
- **Auditoria da retenção:** cada ação do motor grava `acao=retencao.expurgar` ou `retencao.anonimizar`, com contagem nos metadados minimizados e `ator_id=null` (rotina de sistema, `origem=sistema`).

## 6. Desenho

### 6.1 Módulo e fronteiras
Novo módulo `App\Domain\DadosSensiveis` com `README.md`, `Contracts/`, `Services/`, `Models/`, `Database/Factories/`, `Support/` (a chave). O teste `ModularBoundariesTest` passa a cobrir o diretório automaticamente. Binding Contract→Service em `DomainServiceProvider`. `app/Http` não importa `Models`; a chave só é lida dentro do módulo.

### 6.2 Modelos e migration
- **`dado_sensivel_participante`**: `id` (ULID), `participante_id` (string, nullable — FK real no E03), `tipo` (ex.: `laudo`, `diagnostico`, `saude`) , `conteudo_cifrado` (text, nullable) / `referencia_segura` (string, nullable), `finalidade`, `base_legal`, `controle_acesso` (string), `prazo_retencao` (date, nullable), `expurgado_em` (timestamp, nullable), timestamps. Índices: `(participante_id, tipo)`, `(prazo_retencao)`.
- Modelo `DadoSensivelParticipante` com scope `expiraveis(hoje)` (`prazo_retencao <= hoje` e `expurgado_em` nulo) e cast de datas.

### 6.3 Contratos e serviços (módulo DadosSensiveis)
- `ArmazenarDadoSensivel`: `armazenar(armazenador…)` → cifra o conteúdo com a **chave própria** e persiste; valida `conteudo_cifrado` XOR `referencia_segura`.
- `LerDadoSensivel`: `ler(string $id, string $atorId, ?string $escopo = null): string` → decifra, **grava o evento de auditoria** e devolve o texto; 404 se não existe, 403 se `controle_acesso` não permite o ator.
- `ExpurgarRetencao`: `executar(bool $simular): ResultadoRetencao` → percorre `expiraveis`, e para cada registro: se `referencia_segura` preenchida → **anonimiza** (zera referência/conteúdo, marca `expurgado_em`, mantém linha) ; se só `conteudo_cifrado` → **apaga** (soft-safe: zera conteúdo e marca `expurgado_em`); audita cada ação. `simular=true` não escreve.

### 6.4 Chave própria
`Support\CofreChaveSensivel` (ou nome do projeto): monta o `Encrypter` a partir de `SENSITIVE_DATA_KEY`/`_FILE`. `config/privacidade.php` expõe a chave lida; `App\Support` ganha o leitor do padrão `*_FILE` se ainda não existir (verificar antes; a A1 usou `inputs` padrão). **Sem controller/rota** — só contratos.

### 6.5 Retenção configurável
`config/privacidade.php`: `retencao.ativa` e `retencao.prazos`. `ExpurgarRetencao` consulta a config; `ativa=false` ⇒ `simular` forçado. Comando `privacidade:expurgar {--force}`: mostra relatório; com `--force` executa mesmo com `ativa=false` (uso consciente). Auditoria sempre.

### 6.6 Auditoria da leitura e do expurgo
Reusa `RegistrarEvento`. `LerDadoSensivel` exige `atorId` não vazio. `ExpurgarRetencao` grava `origem=sistema`.

## 7. Restrições e decisões do construtor
- Chave por ambiente, nunca no repositório; `*_FILE` em homologação/produção ([ADR-0006](../../06-arquitetura/decisoes/ADR-0006-segredos-backups-e-observabilidade.md)).
- AES-256-GCM; nonce/IV por mensagem (garantido pelo `Encrypter` do Laravel).
- Prazos em **configuração**, com valores do [ADR-0019](../../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md) como default.
- Nenhum dado sensível em log.
- `PHPStan` e `Pint` verdes; `check-endpoints` inalterado (A2 não muda o contrato público).

## 8. Dívidas registradas (A2)
1. **Consumidor real** (E03/E08) e bucket próprio/antivírus (ADR-0005).
2. **Prazos aprovados pelo DPO**; rotina ligada só após aprovação.
3. **`controle_acesso` completo** (matriz por linha) quando o recurso real existir.
4. **Frontend** de dado sensível, quando houver tela.
5. **Rota com contrato OpenAPI** para leitura/gravação sensível, quando o consumidor existir.
6. **Rework da consulta de auditoria** (herdada da A1).

## 9. Critérios de aceite da A2
- **Chave própria:** cifrar/decifrar usa a chave de `SENSITIVE_DATA_KEY`; sem ela, a operação **falha** (nunca usa `APP_KEY`) — teste.
- **Cifra em repouso:** o texto em claro **não** aparece em `conteudo_cifrado` — teste.
- **Leitura auditada:** ler um dado sensível com `ator_id` grava um evento da trilha com `ator_id`, `recurso` e `finalidade` — teste; ler sem ator → erro.
- **Controle de acesso:** ator fora de `controle_acesso` não lê (403) — teste.
- **Retenção desligada por padrão:** `ativa=false` ⇒ nada é apagado e o comando relata (dry-run) — teste; `ativa=true` (ou `--force`) ⇒ registros vencidos são expurgados/anonimizados e cada ação é auditada — teste.
- **Idempotência:** rodar o expurgo duas vezes não duplica efeito nem evento para registro já expurgado — teste.
- Regras cobertas por teste; Pint, PHPStan e `check-endpoints` verdes.

## 10. Ordem de construção (detalhada no plano)
1. Módulo `DadosSensiveis` (esqueleto + README + binding) + config + chave própria + `*_FILE`.
2. Migration + modelo `DadoSensivelParticipante` + factory.
3. `ArmazenarDadoSensivel` (cifra) — teste de cifra em repouso e XOR de colunas.
4. `LerDadoSensivel` (decifra + auditoria + controle de acesso) — teste CA "leitura auditada".
5. `ExpurgarRetencao` + `config/privacidade.php` + comando `privacidade:expurgar` — testes de desligado/ligado/idempotência.
6. Docs: ADR-0031 (A2), CHANGELOG, rastreabilidade, dicionário se preciso.