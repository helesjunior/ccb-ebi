# ADR-0031 — Dados sensíveis e retenção (Etapa 4, sub-etapa A2): escopo e dívidas

**Status:** aceito · **Data:** 10/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-04 (em parte); relaciona ADR-0006, ADR-0019, ADR-0030

## Contexto
A Etapa 4 ataca privacidade e auditoria em sub-etapas. A [ADR-0030](ADR-0030-privacidade-e-auditoria.md) registrou a **sub-etapa A1**, que entregou os mecanismos sobre dados **não sensíveis** e deixou como dívida explícita o que ainda faltava: os **dados sensíveis com chave própria** e a **retenção**. O [plano §Etapa 4](../../07-produto/plano-de-desenvolvimento.md) só considera a etapa completa com esses dois itens, pois é deles que sai a condição de "pronto" — **toda leitura de dado sensível gera registro de auditoria** ([RNF-07](../../01-requisitos/nao-funcionais.md)). Esta ADR registra a **sub-etapa A2**, que entrega o mecanismo, sem tocar na base normativa que ainda aguarda o jurídico e o DPO ([ADR-0019](ADR-0019-base-normativa-lgpd.md)) e sem abrir mão da chave separada da `APP_KEY` ([ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md)). O portão de dados reais continua na **etapa 8**: desenvolvimento e homologação só com **dados fictícios**.

## Decisão
Entregar a sub-etapa A2 com o seguinte escopo:

1. **Módulo segregado `App\Domain\DadosSensiveis`** com a entidade `dado_sensivel_participante`: `conteudo_cifrado` (texto) **ou** `referencia_segura` (para arquivo em bucket próprio), além de `finalidade`, `base_legal`, `controle_acesso` e `prazo_retencao`, e `expurgado_em`. O `participante_id` fica como coluna **sem FK real** até o E03, exatamente como em `manifestacao_privacidade` (A1).
2. **Chave própria, separada da `APP_KEY`.** `App\Domain\DadosSensiveis\Support\CofreChaveSensivel` monta o `Encrypter` a partir de `SENSITIVE_DATA_KEY` (base64 de 32 bytes), com a variante `SENSITIVE_DATA_KEY_FILE` para o padrão twelve-factor. Cifra com **AES-256-GCM**, instanciado **só no módulo**, e **nunca** cai na `APP_KEY`: ausente ou inválida, a primeira leitura/escrita **falha explicitamente** ([ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md)).
3. **Leitura auditada via contrato.** `LerDadoSensivel` decifra, aplica `controle_acesso` (403 para ator fora da lista; 404 se não existe) e grava o evento `acao=dado_sensivel.ler` com `ator_id` e a finalidade. Leitura sem ator é **erro** (não há leitura anônima de dado sensível). A garantia vale para leituras **via `LerDadoSensivel`/contrato** — consumidores futuros devem ler por esse contrato. A tabela em si continua consultável por model direto (o serviço interno faz isso); não há selo de banco bloqueando acesso fora do contrato, então o claim de "pronto" da Etapa 4 (RNF-07) se apoia no mecanismo auditado existir, não em o banco estar fechado.
4. **Retenção configurável e desligada por padrão.** `config/privacidade.php` expõe `retencao.ativa` (**default `false`**) e um **mapa reservado** `retencao.prazos` (tipo→prazo em meses, valores provisórios do [ADR-0019](ADR-0019-base-normativa-lgpd.md)) que **ainda não é consultado**: o motor decide o vencimento pela coluna `prazo_retencao` de cada registro (via `scopeExpiraveis`). O comando operacional `php artisan privacidade:expurgar [--force]` roda o motor `ExpurgarRetencao`: sem `--force` e com `ativa=false` ele apenas **relata** (dry-run) e sai com sucesso, sem apagar nada; com `--force` executa mesmo com a retenção desligada (uso consciente). O expurgo **anonimiza** (zera conteúdo/referência e marca `expurgado_em`, mantendo a linha), é **idempotente** (rodar duas vezes não duplica efeito nem evento) e **auditado** (`acao=retencao.expurgar`, `origem=sistema`, `ator_id=null`).

E as seguintes dívidas ficam **explicitamente fora** da A2:

1. **Consumidor real** de dado sensível (E03/E08) e **bucket próprio/antivírus** ([ADR-0005](ADR-0005-armazenamento-e-antivirus.md)). A A2 só prevê a coluna `referencia_segura`, sem ligar o storage.
2. **Prazos aprovados pelo DPO.** Os da A2 são os provisórios do [ADR-0019](ADR-0019-base-normativa-lgpd.md); a rotina só é ligada após a aprovação.
3. **`controle_acesso` completo** (matriz por linha) quando o recurso real existir; na A2 é textual e resolvido pelo serviço.
4. **Frontend** de dado sensível, quando houver tela (a A2 é backend-only).
5. **Rota com contrato OpenAPI** para leitura/gravação sensível, quando o consumidor existir (sem mudança de contrato público de API na A2).
6. **Rework da consulta de auditoria** (dívida herdada da A1, gatilho de volume). **Auditar negações de leitura** é incremento futuro.

Decisões internas ("meu call", confirmáveis na revisão): cifra AES-256-GCM via `Illuminate\Encryption\Encrypter`, instanciado só no módulo; `controle_acesso` textual (lista de papéis separada por vírgula; `*` libera); gate de "desligado por padrão" no comando (o serviço honra apenas o parâmetro `$executar`); metadados de auditoria minimizados (`tipo`, `finalidade`, `dado_id`).

## Consequências
- A Etapa 4 fecha a condição de "pronto": a **leitura de dado sensível via contrato passa a ser auditada** (RNF-07), com `ator_id` ligando quem leu ao registro da trilha. A tabela em si segue acessível por model direto, então a garantia é do contrato, não do banco.
- A chave de dados sensíveis **sai da `APP_KEY`** e passa a ser um segredo próprio, por ambiente e só lido dentro do módulo ([ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md)); se faltar, a operação falha em vez de cifrar com a chave errada.
- A **rotina de descarte** fica pronta mas **desligada** até o DPO aprovar os prazos; o expurgo é idempotente e auditado, então pode ser reexecutado com segurança.
- As dívidas acima precisam de dono na fase devida: E03/E08 para o consumidor e o bucket; DPO para os prazos; etapa com contrato OpenAPI para a tela de dado sensível.
