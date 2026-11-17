# Design — Etapa 6: Sessões e presença (E04)

**Data:** 13/11/2026 · **Status:** proposto · **Épico:** [E04](../../07-produto/backlog-epicos.md) · **Fase:** 1

## 1. Intenção e por quê

A EBI existe para acolher crianças e adolescentes em encontros regulares. Hoje o sistema
sabe quem são os participantes (E03) e quem pode fazer o quê (E01/E02/E05), mas não sabe
**quando o EBI se encontra** nem **quem esteve presente**. Sem isso, nenhum indicador do
programa (M-01 a M-06) pode ser calculado e a coordenadora continua dependendo de papel.

Esta etapa entrega o ciclo completo do encontro: a agenda que gera as sessões previstas, a
operação do dia (abrir, registrar presença, encerrar) e o registro histórico confiável —
inclusive o estorno com motivo e o lançamento posterior de uma lista em papel.

## 2. Objetivos

1. Configurar a agenda do EBI (dia da semana, horário, vigência) e suas exceções
   (feriado, cancelamento, horário especial) — RF-06.1.
2. Gerar sessões previstas a partir da agenda, sem duplicar e sem sobrepor — RF-06.2/5, CA-23.
3. Operar a sessão: abrir, encerrar informando se foi realizada, cancelar com motivo e
   corrigir sessão encerrada com motivo e auditoria — RF-06.2/4/6, CA-23.
4. Registrar presença em no máximo **3 toques** depois de achar o participante, com
   elegibilidade etária verificada automaticamente — RF-08.1, RN-13..RN-16.
5. Garantir **uma única presença válida** por participante e sessão, mesmo com envios
   simultâneos — CA-02, RN-03.
6. Estornar presença preservando o registro original, autor, motivo e data — CA-13, RN-10.
7. Consultar o histórico de presenças do participante (operador e responsável) — RF-08.3, RF-13.
8. Lançar presenças de uma lista em papel depois do encontro — RNF-03.
9. Avisar quando as presenças passam da equipe mínima informada — ADR-0010.

## 3. Não-objetivos

- **Escala (E07).** Não há designação nominal de equipe nesta etapa. O aviso de equipe
  mínima usa um número informado na sessão (ver §6.8).
- **Geolocalização e sugestão por GPS** (fase 3, RN-04). A seleção da igreja/EBI é sempre
  manual; nada de coordenadas nesta etapa.
- **Presença offline com sincronização** (fase 3, ADR-0016). A contingência desta etapa é o
  lançamento posterior no portal.
- **Relatórios e indicadores (E06).** Nenhum endpoint de relatório; o histórico é por
  participante e por sessão.
- **Notificações** (RF-15) — inclusive o aviso 30 dias antes do 12º aniversário.
- **Reagendamento em massa** de sessões de uma agenda alterada.

## 4. Anti-objetivos

- Registrar presença sem verificar a elegibilidade etária no servidor.
- Duplicar presença: dois envios concorrentes não podem gerar duas presenças válidas.
- Apagar presença estornada, ou "corrigir" sem deixar o registro original e o motivo.
- Deixar a interface ser a autoridade: esconder um botão não substitui a checagem no backend.
- Guardar diagnóstico, laudo ou qualquer dado sensível junto da presença.
- Gerar sessões sobrepostas no mesmo EBI "porque a agenda mudou".

## 5. Abordagens consideradas

| Abordagem | O que entrega | Trade-off | Decisão |
|---|---|---|---|
| **A — Etapa completa** | Agenda, geração, ciclo da sessão, presença, estorno, histórico, contingência, equipe mínima e telas | Maior superfície; duas telas novas e ~22 operações de API | **escolhida** |
| B — Operação do dia primeiro | Sessão criada à mão, presença, estorno, histórico, telas | Entrega mais rápida, mas "sessões previstas" do RF-06 fica incompleto e a agenda volta como retrabalho | descartada |
| C — Backend primeiro | Contrato e serviços completos; telas depois | A meta dos 3 toques e o uso no celular só se provam com tela; deixaria a etapa sem critério de pronto verificável | descartada |

A abordagem A é a única que fecha os critérios de aceite da etapa (CA-02, CA-05, CA-07,
CA-13, CA-17, CA-19, CA-23, CA-26) e a meta de 3 toques. O custo extra em relação a B está
concentrado na agenda e na geração — justamente o que evita criar sessão à mão toda semana.

## 6. Desenho

### 6.1 Módulos e fronteiras (ADR-0002)

Dois módulos, com responsabilidades separadas:

- **`App\Domain\Agenda`** — programação do EBI (`agenda_ebi`) e exceções (`agenda_excecao`).
  Expõe `GerirAgenda` (escrita) e `ConsultarAgenda` (ocorrências de uma data para um EBI).
- **`App\Domain\Sessoes`** — sessões do EBI (`sessao`), correções (`sessao_correcao`) e
  presenças (`presenca`). Expõe `GerirSessaoEbi`, `ConsultarSessao`, `RegistrarPresenca`,
  `EstornarPresenca`, `ConsultarPresenca`, `GerarSessoesPrevistas`.

O módulo `Sessoes` **não lê** tabelas de outros módulos: consome contratos.

| Necessidade do `Sessoes` | Contrato consumido | Onde nasce |
|---|---|---|
| Dados do EBI (igreja, fuso, `participantes_por_auxiliar`) | `Organizacao\Contracts\ConsultarEbi` → `EbiDados` | novo |
| Ocorrências da agenda por data | `Agenda\Contracts\ConsultarAgenda` → `OcorrenciaAgenda` | novo |
| Elegibilidade etária de um participante | `Participantes\Contracts\AvaliarElegibilidade` → `ResultadoElegibilidade` | novo (implementação reusa `AvaliadorElegibilidade` da E03) |
| Busca/identificação de participantes | `Participantes\Contracts\ConsultarParticipante` → `ParticipanteResumo` | novo |
| Propriedade do dependente (histórico) | `Participantes\Contracts\VerificarPropriedadeParticipante` | novo (embrulha `VerificadorPropriedadeParticipante`) |

Ajustes de fronteira necessários:

- `ResultadoElegibilidade` sai de `Participantes\Services` para `Participantes\Contracts`
  (é superfície pública). Os `use` internos e os testes são atualizados.
- `ParticipanteResumo` carrega apenas `id`, `nome`, `matricula`, `igreja_referencia_id` —
  nunca data de nascimento nem exceção. A idade fica dentro do módulo `Participantes`.
- `EbiDados` carrega `id`, `nome`, `igreja_id`, `igreja_nome`, `timezone`,
  `participantes_por_auxiliar`.

**Nomes:** `Sessao` é sempre o **encontro do EBI**. Sessão de dispositivo continua
`SessaoLogin` (Identidade). Nenhum contrato novo usa o nome genérico `Sessao`.

### 6.2 Dados (migrations novas)

```
agenda_ebi        id, ebi_id FK, dia_semana (1..7 ISO), hora_inicio, hora_fim,
                  inicio_vigencia (date), fim_vigencia (date, null = sem fim), timestamps
                  index (ebi_id, dia_semana)

agenda_excecao    id, ebi_id FK, agenda_ebi_id (nullable FK), data (date),
                  tipo (feriado | cancelamento | horario_especial),
                  hora_inicio (nullable), hora_fim (nullable), motivo, timestamps
                  UNIQUE parcial (ebi_id, data, tipo) WHERE agenda_ebi_id IS NULL
                  UNIQUE parcial (agenda_ebi_id, data) WHERE tipo = 'horario_especial'

sessao            id, ebi_id FK, agenda_ebi_id (nullable FK), igreja_id FK,
                  inicio_previsto, fim_previsto (timestamptz),
                  inicio_real, fim_real (timestamptz, nullable),
                  tema, passagem_biblica (nullable),
                  status (prevista | aberta | encerrada | cancelada),
                  realizada (bool, nullable),
                  motivo_cancelamento, cancelada_por (nullable),
                  auxiliares_escalados (smallint, nullable),
                  timestamps
                  UNIQUE parcial (ebi_id, inicio_previsto) WHERE status <> 'cancelada'
                  index (ebi_id, inicio_previsto), index (igreja_id, inicio_previsto)

sessao_correcao   id, sessao_id FK, autor_id FK usuarios, motivo,
                  antes (json), depois (json), ocorrido_em, timestamps

presenca          id, sessao_id FK, participante_id (FK participante),
                  registrado_por FK usuarios, registrado_em (timestamptz),
                  origem (web | app | contingencia),
                  status (valida | estornada),
                  estornado_por (nullable), estornado_em (nullable), motivo_estorno (nullable),
                  timestamps
                  UNIQUE parcial (sessao_id, participante_id) WHERE status = 'valida'
                  index (participante_id, registrado_em)
```

`igreja_id` na sessão é copiado do EBI na criação e nunca muda: é a âncora territorial
(RN-09 — setor e regional derivam do vínculo vigente da igreja, não são congelados).
O fuso vem de `igrejas.timezone` via `ConsultarEbi`.

### 6.3 Agenda e geração de sessões

- **Agenda**: `POST /ebis/{id}/agenda` cria uma faixa semanal; o EBI pode ter **mais de uma
  faixa no mesmo dia da semana** (ex.: manhã e tarde); faixas do mesmo EBI não podem se
  sobrepor no mesmo dia e horário (`422 agenda_sobreposta`).
- **Alterar a agenda não reescreve o passado**: `PATCH /agenda/{id}` só edita a faixa
  enquanto `inicio_vigencia` está no futuro. Se a faixa já começou, o `PATCH` recusa
  (`422 agenda_vigente`) e a mudança é feita encerrando a vigência (`fim_vigencia = ontem`) e
  criando a nova faixa. `DELETE /agenda/{id}` também **encerra** (`fim_vigencia = ontem`), não
  apaga a faixa nem as sessões já geradas; se nada foi gerado ainda, o `DELETE` pode apagar de
  fato a faixa que ainda não começou.
- **Exceções**: `feriado` e `cancelamento` valem para o **dia inteiro** do EBI
  (`agenda_ebi_id` nulo, único por `(ebi_id, data, tipo)`); `horario_especial` vale para uma
  **faixa específica** (`agenda_ebi_id` obrigatório, único por `(agenda_ebi_id, data)`),
  substituindo `hora_inicio`/`hora_fim` daquela faixa naquele dia.
- **Exceção criada depois da geração**: ao registrar `feriado`/`cancelamento`, as sessões
  `prevista` do EBI naquela data são **canceladas** (`motivo = "agenda: <motivo>"`, autor
  registrado, auditado) — feriado não pode deixar sessão de pé só porque já tinha sido
  gerada. Sessões `aberta`/`encerrada` não são tocadas (a sessão aconteceu; corrigir é ato
  explícito). `horario_especial` só vale para gerações futuras, porque mover sessão já
  gerada mudaria o combinado com as famílias.
- **Exceção apagada não ressuscita sessão**: `DELETE /agenda-exceptions/{id}` remove a
  exceção; sessões já canceladas por ela continuam canceladas (a coordenadora reabre criando
  a sessão novamente, se for o caso).
- **Geração** (`GerarSessoesPrevistas::gerar(ebiId, de, ate)`): para cada data do intervalo,
  resolve o dia da semana, aplica exceções, calcula início/fim **no fuso da igreja** e cria o
  que faltar. É idempotente: a restrição única `(ebi_id, inicio_previsto)` e a checagem de
  sobreposição no serviço garantem que repetir a chamada não duplica nem cria intervalo
  conflitante. Limite de 12 semanas por chamada.
- **Sessão cancelada não ressuscita**: a checagem de idempotência considera também as
  canceladas do mesmo EBI e horário previsto. O comando diário nunca recria uma sessão
  cancelada (se a EBI voltou a funcionar naquele horário, a coordenadora cria/cancela
  explicitamente). Só o índice único parcial ignora canceladas, para permitir um novo
  intervalo legítimo.
- **Quando roda**: comando `php artisan sessoes:gerar --dias=28` agendado diariamente no
  container `scheduler`, e `POST /ebis/{id}/sessions/generate` (botão "Gerar próximas
  semanas") para a coordenadora. Os dois usam o mesmo serviço.
- Sessões já criadas nunca são reescritas pela geração; alterar a agenda vale do próximo
  ciclo em diante (exceto as exceções de feriado/cancelamento, acima).

### 6.4 Ciclo da sessão (RF-06, CA-23)

```
prevista --abrir--> aberta --encerrar(realizada)--> encerrada --corrigir(motivo)--> encerrada
prevista --cancelar(motivo)--> cancelada
```

- `abrir`: só de `prevista`; grava `inicio_real = agora`. Fora disso, `409 sessao_em_estado_invalido`.
- `encerrar`: só de `aberta`; exige `realizada` (sim/não) e grava `fim_real` (padrão agora).
  Só `encerrada` com `realizada = true` conta em M-05.
- `cancelar`: só de `prevista`; exige `motivo`. Sessão aberta ou encerrada não é cancelada —
  é corrigida.
- `corrigir` (`PATCH /sessions/{id}`): campos `tema`, `passagem_biblica`, `inicio_real`,
  `fim_real`, `realizada` e `auxiliares_escalados`. Quando a sessão está `encerrada`, exige
  `motivo`; grava a linha em `sessao_correcao` (antes/depois) e o evento `sessao.corrigida` na
  auditoria. Alterar `realizada` muda a contagem de M-05 e por isso é sempre auditado.
  `auxiliares_escalados` é ajustável em qualquer status e exige motivo apenas quando a sessão
  já está `encerrada` (§6.8).
- **Duplicidade e sobreposição**: intervalo `[inicio_previsto, fim_previsto)` do mesmo EBI não
  pode se sobrepor a outra sessão não cancelada (`422 sessao_sobreposta`). Não há exceção
  autorizada nesta etapa (§8).

### 6.5 Presença e elegibilidade (RF-08, RN-02/03, CA-02/05/17/19/26)

Fluxo de registro:

1. `GET /sessions/{id}/participants?busca=&finalidade=` devolve participantes que casam com
   nome ou matrícula, **já com o resultado da elegibilidade** para a data da sessão:
   `{ id, nome, matricula, igreja_referencia, elegivel, motivo_bloqueio }`. O operador vê de
   antemão quem não pode entrar, sem precisar clicar.

   **A busca é nacional** (decisão de produto): o visitante de outra igreja (FL-02) precisa ser
   registrado no EBI onde está hoje, e a matrícula é única nacionalmente (RN-01). Para não
   transformar isso em leitura ampla sem controle, a busca devolve só identificação mínima
   (nome, matrícula, igreja de referência — nunca nascimento, exceção ou dado sensível), exige
   `finalidade` declarada e grava o evento `participante.buscado` com ator, termo e sessão.
   A busca só aceita termo com 3+ caracteres e devolve no máximo 20 resultados, ordenados por
   nome. Sem isso, a rota é recusada (`422 finalidade_obrigatoria`).
2. `POST /sessions/{id}/attendance` grava a presença. O servidor reavalia a elegibilidade
   (nunca confia no passo 1), exige sessão `aberta` e devolve `201` com
   `{ presenca, aviso? }`.
3. Presença já existente e válida (mesmo participante/sessão) devolve `200` com o registro
   atual — idempotente, inclusive sob corrida (índice único parcial, CA-02). A rota também
   aceita `Idempotency-Key`.
4. Presença simultânea em outra sessão de igreja diferente no mesmo dia devolve
   `aviso: presenca_simultanea` (informativo, não bloqueia — RF-08.4).

Elegibilidade (reusa `AvaliadorElegibilidade`, já testado na E03):

- `menos de 3 anos` → bloqueado sempre, mesmo com exceção (`idade_minima`, CA-19).
- `3 a 11 anos` → permitido.
- `12 anos ou mais` → só com exceção `aprovada` e vigente na data local da sessão
  (`excecao_ausente_ou_invalida`, CA-17/RN-15).
- A idade usa a **data local da sessão no fuso da igreja** e a regra de 29/02 → 01/03
  (RN-16, CA-26). O cálculo continua num só lugar.
- Bloqueio devolve `422 inelegivel` com `motivo_bloqueio`; nada é gravado.

Dados gravados na presença: `sessao_id`, `participante_id`, `registrado_por`,
`registrado_em`, `origem`. Igreja e EBI vêm da sessão — não são copiados para a presença.
Nenhum dado de saúde ou diagnóstico entra aqui.

### 6.6 Estorno (RF-08.5, CA-13, RN-10)

- `POST /attendance/{id}/reversal` com `{ motivo }` (obrigatório, 3..500 caracteres).
- A presença passa a `estornada` e ganha `estornado_por`, `estornado_em`, `motivo_estorno`;
  nada é apagado. Um novo registro válido pode ser criado depois (RN-03).
- Autoriza `presenca.estornar` (adm_igreja e coordenadora do EBI, conforme a matriz).
- Audita `presenca.estornada` com motivo e autor; auditar estorno já cobre a exigência de
  "preserva o original".

### 6.7 Contingência em papel (RNF-03)

Quando o sistema cai, a lista é preenchida à mão. Depois:

- `POST /sessions/{id}/contingency-attendance` aceita presença em sessão **encerrada**
  (não em cancelada), exige `motivo` (ex.: "queda de energia — lista em papel") e grava
  `origem = contingencia`; audita `presenca.contingencia`.
- Autoriza `sessao.gerenciar` (adm_igreja ou coordenadora), que são quem responde pela
  conferência da lista.
- Uma presença por chamada nesta etapa (o lote fica como dívida, §8). O botão "lançar em
  papel" na tela de presença só aparece para sessão encerrada e para esses perfis.

### 6.8 Equipe mínima (ADR-0010)

A fórmula é `auxiliares = ⌈participantes ÷ equilíbrio⌉`, com o divisor do EBI
(`ebis.participantes_por_auxiliar`, padrão 4). A escala nominal é E07; nesta etapa a sessão
carrega `auxiliares_escalados` (inteiro, opcional), preenchido pela coordenadora na tela da
sessão. O detalhe da sessão devolve:

```
equipe_minima: {
  presentes, participantes_por_auxiliar,
  auxiliares_necessarios, auxiliares_informados,
  alerta: bool          // false quando auxiliares_escalados é null
}
```

O alerta **não bloqueia** o registro (ADR-0010); a tela mostra uma faixa de aviso. Quando a
E07 chegar, `auxiliares_escalados` passa a ser derivado da escala aprovada.

### 6.9 Autorização (matriz + ABAC)

Novas operações em `Identidade\Support\Operacoes`, com as linhas correspondentes em
`AutorizadorOperacao::MATRIZ` e `Gate::define` + Policy:

| Operação | Papéis | Escopo |
|---|---|---|
| `agenda.gerenciar` | adm_igreja, coordenadora | igreja / EBI |
| `sessao.ler` | super_admin, adm_regional, adm_setor, adm_igreja, ministerio_local, coordenadora, auxiliar | hierarquia |
| `sessao.gerenciar` | adm_igreja, coordenadora | igreja / EBI |
| `presenca.ler` | super_admin, adm_regional, adm_setor, adm_igreja, ministerio_local, coordenadora, auxiliar | hierarquia |
| `presenca.registrar` | coordenadora, auxiliar | EBI |
| `presenca.estornar` | adm_igreja, coordenadora | igreja / EBI |

Regras aplicadas:

- O tipo de recurso para escopo é **`ebi`** (id da sessão/da EBI). Nas rotas cujo id é de
  sessão, presença ou faixa de agenda, o serviço resolve o registro até a EBI **antes** de
  chamar o gate — nunca se confia no `ebi_id` enviado pelo cliente. `ResolverEscopo::cobre`
  sobe a hierarquia, então coordenadora/auxiliar (EBI) e adm_igreja (igreja) funcionam com a
  mesma checagem.
- RN-02: registrar presença exige vínculo vigente com aquela EBI (o escopo do contexto de
  atuação, revalidado pelo middleware `X-Acting-Scope`).
- **Busca de participantes da sessão**: autorizada por `presenca.registrar` **ou**
  `presenca.ler`, porque `adm_igreja` não registra presença mas opera a contingência e
  precisa encontrar quem lançar. A busca nacional é auditada com finalidade (§6.5); a
  restrição territorial vale para a *sessão* (o ator precisa cobrir aquela EBI), não para o
  participante encontrado — é o que faz o visitante funcionar.
- O histórico do responsável (`GET /me/participants/{id}/attendance`) é por **propriedade**
  (`VerificarPropriedadeParticipante`), não por território.
- Matriz literal: super_admin **não** gerencia sessão nem estorna presença (só lê), e
  adm_regional/adm_setor também não — nesta etapa vale a matriz como está.

### 6.10 Contrato e telas

Ordem obrigatória: **OpenAPI → catálogo (`docs/04-api/endpoints.md`, linha com `no OpenAPI`)
→ código**, `scripts/check-endpoints.py` verde e `schema.d.ts` regenerado.

| Método | Caminho | Operação |
|---|---|---|
| GET | `/me/ebis` | autenticado (escopos do próprio usuário) |
| GET | `/ebis/{id}/agenda` | `sessao.ler` |
| POST | `/ebis/{id}/agenda` | `agenda.gerenciar` |
| PATCH | `/agenda/{id}` | `agenda.gerenciar` |
| DELETE | `/agenda/{id}` | `agenda.gerenciar` |
| GET | `/ebis/{id}/agenda-exceptions` | `sessao.ler` |
| POST | `/ebis/{id}/agenda-exceptions` | `agenda.gerenciar` |
| DELETE | `/agenda-exceptions/{id}` | `agenda.gerenciar` |
| GET | `/ebis/{id}/sessions` | `sessao.ler` |
| POST | `/ebis/{id}/sessions/generate` | `agenda.gerenciar` |
| GET | `/sessions/{id}` | `sessao.ler` |
| POST | `/sessions/{id}/open` | `sessao.gerenciar` |
| POST | `/sessions/{id}/close` | `sessao.gerenciar` |
| POST | `/sessions/{id}/cancel` | `sessao.gerenciar` |
| PATCH | `/sessions/{id}` | `sessao.gerenciar` |
| GET | `/sessions/{id}/participants` | `presenca.registrar` ou `presenca.ler` |
| POST | `/sessions/{id}/attendance` | `presenca.registrar` |
| POST | `/sessions/{id}/contingency-attendance` | `sessao.gerenciar` |
| GET | `/sessions/{id}/attendance` | `presenca.ler` |
| POST | `/attendance/{id}/reversal` | `presenca.estornar` |
| GET | `/participants/{id}/attendance` | `presenca.ler` |
| GET | `/me/participants/{id}/attendance` | propriedade |

**Telas** (React + shadcn, dentro da casca; sem roteador — o estado `Tela` ganha `sessoes` e
`presenca`):

- **Sessões** (`sessoes`): seletor de EBI entre os escopos do usuário, filtro de período,
  lista agrupada por dia com situação (`prevista`, `aberta`, `encerrada`, `cancelada`),
  ações (abrir, encerrar, cancelar, corrigir) e o botão "Gerar próximas semanas" quando não
  houver sessão no período. A agenda (faixas e exceções) fica num painel desta tela.
- **Presença** (`presenca`, aberta a partir de uma sessão): cabeçalho com EBI, data e
  situação; busca por nome/matrícula com debounce; lista de resultados com selo
  "elegível"/"bloqueado: motivo"; botão de registro por linha; lista dos presentes com hora e
  botão de estorno (diálogo com motivo); contador e faixa de equipe mínima; botão de
  contingência quando a sessão está encerrada.
- **Histórico em Participantes e Dependentes**: diálogo de histórico de presenças do
  participante (data, EBI, situação), reutilizando o mesmo endpoint por perfil.

Meta de interação: digitar o nome (1), tocar no participante (2) e confirmar (3). O registro
mostra o resultado sem navegar para outra tela.

**Seletor de EBI (`GET /me/ebis`)**: `/me/scopes` devolve só `papel`/`tipo_escopo`/`escopo_id`,
o que não permite montar a lista de EBIs no cliente para adm_regional/adm_setor. O endpoint
novo resolve as EBIs alcançadas pelos vínculos vigentes do usuário **no contexto de atuação**
e devolve `{ id, nome, igreja_id, igreja_nome, timezone }`. Sem gate próprio: a lista é sempre
recortada pelos vínculos de quem pede (nunca "todas as EBIs").

### 6.11 Tempo, fuso e ordenação

- `inicio_previsto`/`fim_previsto`/`registrado_em` são `timestamptz`; a conversão para
  exibição usa o fuso da igreja da sessão.
- A data que decide idade é a **data local da sessão no fuso da igreja**.
- A agenda é semanal por dia da semana ISO (1 = segunda) e horário local da igreja.

## 7. Restrições

- Fronteira modular: `app/Http` não importa modelos de módulo; módulos só consomem
  `Contracts` de outros (o teste de arquitetura da suíte inteira cobre isso).
- TDD no backend (Pest); testes de tabela para idade/29-02; testes de corrida/idempotência
  para presença; testes de fronteira horizontal e vertical para autorização.
- Frontend sem runner de teste: validação por `npm run lint`, `npm run build` e verificação
  manual no navegador (ADR-0022).
- Escritas críticas com `Idempotency-Key`; presença também é idempotente pelo índice único.
- Sem Laravel Boost (decisão do projeto).
- Datas e nomes de domínio em português, como no resto do código.

## 8. Dívidas registradas (desta etapa)

1. **Equipe mínima sem escala (E07)**: `auxiliares_escalados` é informado à mão. Quando a
   E07 existir, o campo passa a ser derivado da escala aprovada.
2. **Sem exceção autorizada para sobreposição** (RF-06.5): intervalos conflitantes são sempre
   bloqueados; não há "forçar" nesta etapa.
3. **Contingência em lote**: uma presença por chamada; importar a lista inteira fica para
   quando houver uso real de papel.
4. **`presenca.ler` com `finalidade` (`L*`)**: a busca de participantes já exige finalidade
   (§6.5); a exigência nas **listas/histórico** para super_admin/adm_regional/adm_setor fica
   para a E06 (relatórios), quando essas leituras passarem a ser usadas de fato. Nesta etapa
   vale papel + escopo territorial.
5. **Consulta (A) não lê presença identificável**: o agregado por período é E06.
6. **Aviso 30 dias antes do 12º aniversário** (RF-15) não entra aqui.
7. **CA-27 — presença no escopo de mesclagem/correção (dívida herdada da E03)**: nesta etapa
   a presença **não** entra no cálculo de "ligado ao EBI" que autoriza mesclar/corrigir.
   Fechar na rodada seguinte: contrato de `Sessoes` consumido por
   `app/Support/Participantes/AutorizacaoParticipante` (direção Participantes → contrato de
   Sessoes, sem ciclo) + testes de CA-27. Escolha registrada: manter a Etapa 6 no escopo.

### 8.1 Resoluções do builder check (registro)

Pontos que o revisor levantou e que foram decididos aqui, com o motivo:

- **Busca nacional vs. EBI** — decidido com o produto: nacional, com finalidade obrigatória,
  campos mínimos e auditoria da busca (§6.5).
- **`adm_igreja` sem `presenca.registrar`** — a busca da sessão aceita
  `presenca.registrar` **ou** `presenca.ler`; a contingência continua sob `sessao.gerenciar`
  (§6.9). Sem isso o adm_igreja veria o botão e não conseguiria usar.
- **Sessão cancelada ressuscitando** — a idempotência da geração considera canceladas e o
  índice único parcial só existe para permitir novo intervalo legítimo (§6.3).
- **Exceção criada depois da geração** — feriado/cancelamento cancela as `prevista` daquele
  dia (auditado); horário especial vale só para gerações futuras (§6.3).
- **Seletor de EBI no cliente** — `GET /me/ebis`, recortado pelos vínculos e pelo contexto de
  atuação (§6.10).
- **Mais de uma faixa no mesmo dia da semana** — permitido; `horario_especial` é por faixa
  (`agenda_ebi_id` obrigatório) e `feriado`/`cancelamento` são por dia (§6.3).

## 9. Critérios de aceite

| Critério | Como esta etapa demonstra |
|---|---|
| CA-02 — presença idempotente | Teste com dois envios concorrentes/duplicados: uma presença válida, segunda chamada devolve a existente |
| CA-05 — idade | Teste de tabela: véspera do 12º aniversário permitido, dia do aniversário bloqueado, exceção vigente permite |
| CA-07 — GPS desabilitado | Seleção manual de EBI/igreja na tela; nenhum caminho depende de coordenadas |
| CA-13 — auditoria do estorno | Presença estornada mantém original, motivo, autor e data; evento auditado |
| CA-17 — exceção revogada/expirada | 12+ sem exceção vigente → `422 inelegivel`; histórico anterior continua visível |
| CA-19 — menores de 3 anos | Bloqueado mesmo com exceção registrada |
| CA-23 — sessão duplicada e correção | Geração repetida não duplica; sobreposição → `422`; correção de encerrada exige perfil, motivo e audita |
| CA-26 — 29/02 e fuso | Teste de tabela com nascido em 29/02 e sessão em fuso diferente de UTC |
| RNF-03 — contingência | Presença de lista em papel entra em sessão encerrada, com motivo, `origem = contingencia` e evento auditado |
| ADR-0010 — equipe mínima | Sessão com presença acima do que os auxiliares informados cobrem exibe alerta e **não** bloqueia o registro |
| 3 toques | Verificação manual: busca → participante → confirmar, sem trocar de tela |

## 10. Ordem de construção (detalhada no plano)

1. Contrato (OpenAPI + catálogo) e operações de autorização.
2. Fronteira com os módulos vizinhos (`ConsultarEbi`, `AvaliarElegibilidade`,
   `ConsultarParticipante`, `VerificarPropriedadeParticipante`) e o `GET /me/ebis` do seletor
   de EBI.
3. Agenda e exceções (modelo, serviço, endpoints, testes).
4. Geração de sessões (serviço idempotente, comando agendado, endpoint).
5. Ciclo da sessão (abrir, encerrar, cancelar, corrigir) e auditoria.
6. Presença: busca com elegibilidade, registro idempotente, aviso de simultaneidade.
7. Estorno e histórico (operador e responsável).
8. Contingência em papel e equipe mínima.
9. Telas: Sessões e Presença; histórico no perfil do participante e do dependente.
10. Documentação (CHANGELOG, README, dívidas) e gates completos.
