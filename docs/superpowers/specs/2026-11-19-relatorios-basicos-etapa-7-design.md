# Design — Relatórios básicos (Etapa 7 · E06)

**Data:** 19/11/2026 · **Épico:** E06 · **Requisitos:** RF-14, M-01 a M-05, M-09, ADR-0020, ADR-0029 · **Critérios:** CA-14, CA-18

## Intenção e por quê

Hoje a presença é registrada (Etapa 6), mas ninguém acima da sessão consegue ver o resultado. Cada perfil de acompanhamento precisa enxergar **o próprio território** e descer na hierarquia:

| Perfil | Abre em | Pode descer até |
|---|---|---|
| Coordenadora | EBIs a que está vinculada | — (EBI é a folha) |
| Ministério Local | Igrejas a que está vinculado | EBIs da igreja |
| Ministério Setorial | Setor | Igrejas → EBIs |
| Ministério Regional | Regional | Setores → Igrejas → EBIs |
| Ministério Central | Central | Regionais → Setores → Igrejas → EBIs |

Não existe uma tela por perfil: é **um único relatório**, que começa no escopo de quem está olhando (o contexto de atuação, `X-Acting-Scope`). O relatório e a exportação mostram só números, nunca nomes.

## Objetivos

1. Mostrar, para um nó da hierarquia:
   - **Indicadores do período escolhido:**
     - **M-02**: participantes distintos;
     - **M-03**: presenças válidas;
     - **M-05**: sessões realizadas;
     - **M-04**: média por sessão (M-03 ÷ M-05).
   - **Indicadores na data em que o relatório é gerado:**
     - **M-01**: participantes cadastrados;
     - participantes ativos;
     - **M-09**: igrejas com EBI ativo.
2. Mostrar os mesmos números para cada filho direto do nó, permitindo navegar de cima para baixo.
3. Calcular **M-02 sempre sobre as presenças**: o valor de um nó nunca é a soma dos filhos (CA-14).
4. Atribuir sessões e participantes ao setor e à regional **vigentes** da igreja (RN-09 / CA-18).
5. Aplicar a supressão do ADR-0020 a quem atua acima da igreja, com a cascata da decisão 6a. A proteção vale dentro de uma mesma resposta; a dedução comparando consultas diferentes é risco aceito (ver Riscos).
6. Exportar em CSV os mesmos números da tela, já suprimidos.
7. Fazer o relatório declarar período, fuso, abrangência, critérios de exclusão e o momento em que foi gerado (RF-14.6).

## Não-objetivos (cortados e por quê)

- **M-10, taxa de comparecimento:** a fórmula não foi aprovada (RF-14.3).
- **M-06, cobertura da escala:** depende da escala nominal (E07), que ainda não existe.
- **Exportação com nomes:** continua proibida (ADR-0029).
- **XLSX e PDF:** o CSV atende a exportação agregada pedida para esta etapa. Os outros formatos podem sair depois do mesmo cálculo.
- **Gráficos e séries no tempo:** o MVP usa tabela com totais.
- **Tabelas pré-calculadas ou cache:** o volume do piloto de Brasília não justifica (ver Riscos).
- **Relatório para Auxiliar e Responsável:** pela matriz, os dois não têm acesso a relatórios.

## Restrições

- **Fronteira de módulos (ADR-0002):** sessões e presenças são do módulo `Sessoes`, participantes do `Participantes` e a hierarquia do `Organizacao`. Um módulo não lê tabela do outro.
- **OpenAPI antes do código:** a rota entra no `openapi.yaml`, recebe a marca `no OpenAPI` em `endpoints.md` e só depois o `schema.d.ts` é regenerado.
- **Autorização no servidor:** papel × operação (`AutorizadorOperacao::MATRIZ`) e nó dentro do escopo (`ResolverEscopo::cobre`). O `AutorizadorOperacao` devolve o mesmo `false` nos dois casos, e o `SessaoController` responde 403 fora do território. Por isso a separação entre 403 e 404 é feita explicitamente nesta entrega (decisão 3).
- **Dados reais do modelo:**
  - A `sessao` guarda só `igreja_id` e `ebi_id`.
  - O `participante` tem **no máximo uma** `igreja_referencia_id`. O campo é opcional e não existe vínculo de participante com EBI.
  - O participante pode ter presença em várias igrejas.
  - Uma igreja pode ter vários EBIs (`Igreja::ebis`).
  - Setor e regional vêm sempre do vínculo vigente da igreja.

## Abordagens consideradas

- **A — Um endpoint "nó + filhos", calculado na hora (escolhida).** `GET /reports/attendance?nivel=&id=&inicio=&fim=` devolve os números do nó e de cada filho direto. A tela navega chamando o mesmo endpoint para o filho clicado. É uma consulta só, que serve os cinco perfis, todos os níveis e a exportação.
- **B — Tabela de agregados alimentada por job.** É mais rápida em volume nacional, mas traz uma tabela nova, um job, a defasagem de quando os números foram atualizados e a obrigação de recalcular a cada estorno, lançamento de contingência, mesclagem e transferência de igreja (CA-18). Seria custo antecipado para o volume do piloto.
- **C — Um endpoint por nível** (`/reports/regions`, `/reports/sectors`…). Cada rota faz a mesma coisa, então o contrato e a autorização se repetem cinco vezes sem ganho.

## Decisões

1. **Contrato.** `GET /api/v1/reports/attendance`:
   - Parâmetros: `nivel` (`central|regional|setor|igreja|ebi`), `id`, `inicio` e `fim` (datas `YYYY-MM-DD`, ambas inclusivas e obrigatórias, intervalo de no máximo 366 dias). Sem `nivel`/`id`, o relatório usa o nó do contexto de atuação. `formato` é `json` (padrão) ou `csv` (decisão 10).
   - Resposta: `{ data: { no, periodo, fuso, gerado_em, criterios, totais, filhos: [{ nivel, id, nome, totais }] } }`.
   - `totais` traz `participantes_distintos`, `presencas`, `sessoes_realizadas`, `media_por_sessao`, `participantes_cadastrados`, `participantes_ativos` e `igrejas_com_ebi_ativo`.
   - Cada indicador é um objeto `{ valor, suprimido, motivo }`:
     - `motivo = limiar`: o próprio valor está entre 1 e 4.
     - `motivo = filho_suprimido`: o valor foi escondido pela cascata da decisão 6a.
     - `motivo = nao_se_aplica`: o indicador não existe naquele nível (decisão 5). Nesse caso `suprimido` é `false`.
     - Sem motivo: o número aparece normalmente.
   - `GET /reports/participants`, que estava no catálogo, não é criado: M-01 e participantes ativos saem no mesmo endpoint. A linha correspondente em `endpoints.md` é removida (aprovado pelo produto).
2. **Nova operação `relatorio.ler`.**
   - Entra em `Operacoes` e na `MATRIZ` para `super_admin`, `adm_regional`, `adm_setor`, `adm_igreja`, `ministerio_central`, `ministerio_regional`, `ministerio_setorial`, `ministerio_local` e `coordenadora`.
   - Os administradores entram por equivalência, definida pelo produto: Super Admin = Ministério Central, Adm Regional = Ministério Regional, Adm Setorial = Ministério Setorial, Adm Igreja = Ministério Local da igreja e Coordenadora = Coordenadora.
   - Cada par vê exatamente o mesmo relatório, no mesmo território. Não há perfil extra.
3. **Escopo, em três checagens nesta ordem.**
   1. Papel sem `relatorio.ler` → **403**. A checagem é só de papel, sem recurso-alvo.
   2. Nó inexistente → **404**.
   3. Nó fora da subárvore do contexto (`ResolverEscopo::cobre`) → **404**.

   Contexto forjado ou de vínculo revogado já é recusado pelo `ResolvedorContextoAtuacao` com 403 `acting_scope_invalido`, sem mudança. O nível da folha é o EBI, sem filhos. A coordenadora com mais de um EBI troca de EBI pelo seletor de contexto que já existe.
4. **Onde cada cálculo mora.**
   - **`Organizacao`** ganha, no contrato `ConsultarHierarquia`:
     - `filhos(tipo, id)`: id, nome e tipo dos filhos diretos;
     - `igrejasDaSubarvore(tipo, id)`: ids das igrejas sob o nó, pelo vínculo vigente.

     Para o nível EBI, a subárvore é o próprio EBI.
   - **`Sessoes`** ganha o contrato `ConsultarIndicadoresPresenca`, com dois métodos:
     - os totais do período por igrejas ou EBIs, numa consulta agregada com `DISTINCT` sobre o id canônico;
     - os ids canônicos com presença válida desde uma data, usados para "participante ativo";
     - mais o "EBI ativo" de cada EBI (sessão realizada nos últimos 90 dias).
   - **`Participantes`** ganha um contrato com dois métodos:
     - o mapa duplicado → principal das mesclagens vigentes;
     - a contagem de cadastrados e de ativos por `igreja_referencia_id`.
   - **`Relatorios`**, um serviço novo, só orquestra: escopo → hierarquia → indicadores → supressão em cascata.
   - Assim cada módulo lê apenas as próprias tabelas, e o CA-18 sai de graça: a lista de igrejas vem do vínculo vigente.
5. **Critérios de contagem.**
   - **M-03:** presenças `valida` (as estornadas ficam de fora).
   - **M-05:** sessões `encerrada` com `realizada = true`. Uma sessão realizada sem nenhuma presença também conta.
   - **M-02:** participantes distintos dessas presenças, contados pelo **id canônico**.
     - A mesclagem não move presenças: `GerenciadorMesclagem.php:75–79` move só os vínculos.
     - O id canônico vem do mapa duplicado → principal entregue por `Participantes`. O mapa sai de `snapshot_mesclagem` do principal e do `status = mesclado` do duplicado.
     - **Cadeia:** o `GerenciadorMesclagem` permite mesclar um principal anterior num terceiro (B → A → C) e não rejeita principal já mesclado, o que permite ciclo. Por isso o mapa já sai **resolvido** até o representante final: B → C e A → C. Quem consome nunca segue cadeia.
     - **Ciclo:** a resolução guarda os ids visitados. Ao reencontrar um, os participantes do ciclo contam como uma pessoa só, com o menor id do ciclo como canônico. A escolha é técnica: dá um resultado determinístico e não derruba o relatório.
     - Desfazer a mesclagem tira o par do mapa, sem rotina extra. Desfazer A → C, com B → A ainda vigente, deixa B → A.
     - **Alternativa descartada:** mover as presenças na mesclagem. Mexe na Etapa 6 e exige guardar as presenças movidas no snapshot para desfazer.
     - **Limites conhecidos, fora desta entrega (ver Riscos):** uma segunda mesclagem no mesmo principal sobrescreve o snapshot (`:72`), e a mesclagem aceita cadeia e ciclo (`:43–48` só checa o duplicado).
   - **M-04:** calculado com uma casa decimal, ou `null` quando M-05 = 0.
   - **Período:** pela data local de `inicio_previsto` da sessão no fuso da igreja (a mesma regra de fuso da RN-16). O relatório declara o fuso. O padrão é "últimos 30 dias", com no máximo 366 dias.
   - **Atribuição territorial do participante** (leitura mais conservadora, confirmada pelo produto):
     - Vale para M-01 e participantes ativos.
     - Cada participante conta **em uma única igreja**: a da sua `igreja_referencia_id`. Pela igreja, ele conta também no setor, na regional e na Central vigentes.
     - Participante sem igreja de referência **não conta em nenhum nó**, nem na Central. Os critérios do relatório dizem isso, sem informar quantos são.
     - Presença em outras igrejas **não** atribui o participante a elas.
     - Como cada participante conta uma vez só, M-01 e ativos se somam entre filhos, e a cascata (decisão 6a) cobre a dedução.
     - **Nível EBI:** o modelo não liga o participante a um EBI, e uma igreja pode ter vários. No nó EBI, M-01 e ativos vêm com `motivo = nao_se_aplica`. A coordenadora vê esses dois números no nível da igreja apenas se também tiver contexto de igreja.
   - **M-01, participantes cadastrados:** `status = ativo` (exclui `inativo` e `mesclado`, de modo que o duplicado não conta duas vezes), atribuído como acima. É uma fotografia na data em que o relatório é gerado; o período escolhido não interfere.
   - **Participantes ativos:** o ADR-0020 define "pelo menos uma presença válida nos últimos 12 meses, ou cadastro feito nos últimos 90 dias".
     - Interpretação usada: entre os participantes do M-01, os que têm presença válida em **qualquer** igreja nos 12 meses anteriores à data de geração (contada pelo id canônico) ou `created_at` nos 90 dias anteriores.
     - A presença só qualifica o participante como ativo. Ele continua atribuído à igreja de referência.
     - A data de referência é a de geração do relatório, não o `fim` do período.
   - **M-09, igrejas com EBI ativo:** igrejas da subárvore com ao menos um EBI que teve sessão realizada nos 90 dias anteriores à data de geração. Segue o ADR-0020, confirmado pelo produto; a linha 15 da página de métricas ("EBI com status `ativo`") é corrigida para essa definição.
     - No nó EBI, `nao_se_aplica`.
     - No nó igreja, vale 0 ou 1.
     - **Nunca é suprimido.** M-09 conta igrejas, não pessoas, e fica fora da regra de supressão em todos os níveis: não passa pelo limiar e não dispara a cascata. O M-09 de uma igreja só pode ser 0 ou 1, então esconder o 1 entregaria o valor exato.
6. **Supressão (ADR-0020, fronteira "acima da igreja").**
   - **Quem vê o número real:** coordenadora, adm_igreja e Ministério Local, que são equivalentes. A igreja e tudo abaixo dela veem números reais. A antiga exceção da "equipe do próprio EBI" deixa de existir como exceção separada, porque está contida nessa regra.
   - **Quem recebe supressão:** os perfis acima da igreja, ou seja, Super Admin/Ministério Central, Adm Regional/Ministério Regional e Adm Setorial/Ministério Setorial.
   - **A isenção depende do papel e do tipo de escopo do contexto.** O número real só aparece quando o papel é coordenadora, adm_igreja ou ministerio_local **e** o `tipo_escopo` do contexto é `igreja` ou `ebi`. Qualquer outra combinação recebe supressão.
     - Motivo: o código aceita conceder qualquer papel em qualquer escopo (`VinculoRequest` aceita qualquer `tipo_escopo`; `GerenciadorVinculo::conceder` checa papel e território separadamente).
     - Barrar essas concessões incompatíveis no cadastro de vínculos é mudança na Identidade, fora desta entrega. Fica registrada em Riscos.
   - **Limiar:** para quem recebe supressão, todo indicador de pessoas ou sessões (M-01, ativos, M-02, M-03, M-05) com valor de 1 a 4 volta `valor: null`, `suprimido: true`, `motivo: limiar`. Zero aparece como zero. `media_por_sessao` some (com o motivo herdado) quando M-03 ou M-05 forem suprimidos.
   - Tudo é aplicado **no servidor**, na resposta JSON e no CSV.
6a. **Supressão complementar em cascata.** Decisão do produto, para impedir que um número escondido seja descoberto por subtração ou navegação.
   - **Regra, avaliada de baixo para cima, das folhas (EBIs) até a Central:**
     - Um nó fica **suprimido** quando algum indicador dele foi escondido pelo limiar, ou quando algum filho direto está suprimido.
     - Se algum filho direto está suprimido, por qualquer motivo, **todos** os indicadores suprimíveis do nó voltam `valor: null`, `suprimido: true`, `motivo: filho_suprimido`. Isso inclui M-02, M-03, M-05, a média, M-01 e ativos. O M-09 não é indicador de pessoas e continua visível (decisão 5).
   - **Quem dispara:** só indicadores de pessoas ou sessões, com o mesmo limiar de 5 do ADR-0020. Zero e `nao_se_aplica` não disparam.
   - **Mesma representação em todo lugar.** A situação de um nó depende só da sua subárvore, então é a mesma em qualquer nível em que ele apareça: como nó aberto ou como linha no relatório do pai. Um setor suprimido aparece suprimido também na linha da regional, o que suprime a regional, e assim até a Central.
   - **Motivo na linha do pai.** Cada linha de filho mostra o motivo do próprio filho: `limiar` se ele mesmo tem valor de 1 a 4, ou `filho_suprimido` se foi escondido pela cascata. Os totais do nó aberto recebem `filho_suprimido`.
   - **Quando um indicador se qualificaria pelos dois motivos**, prevalece `filho_suprimido`. Esse motivo revela menos, porque não diz que o valor é pequeno.
   - **Abrangência:** vale sempre que quem consulta atua acima da igreja, em qualquer nó que ele abra, **inclusive um nó `igreja`**. Uma igreja com EBIs de 3 e 7, aberta por um setorial, esconde o total 10.
   - **Custo aceito:** em nós com algum descendente pequeno, o número do próprio nó desaparece, mesmo que seja grande. A cascata pode esconder totais de setores, regionais e da Central inteira.
7. **Sem auditoria de leitura nem finalidade.** O relatório não identifica ninguém, então não cai no `*` da matriz. O `L*` (relatório com nomes) dos administradores continua fora desta entrega.
8. **Tela.**
   - Novo item "Relatórios" no menu, visível para quem tem `relatorio.ler`.
   - Escolha de período, com o padrão "últimos 30 dias" e no máximo 366 dias.
   - Um cartão com os totais do nó e uma tabela com os filhos, em que cada linha leva ao filho.
   - Os indicadores na data de geração (M-01, ativos, M-09) ficam separados dos indicadores do período, com o rótulo "na data de hoje".
   - Uma trilha (Central › Regional › …) permite voltar só até o nó do contexto de atuação.
   - **Rótulos:**
     - `limiar` → "< 5";
     - `filho_suprimido` → "Não divulgado", com o motivo "uma unidade abaixo tem menos de 5";
     - `nao_se_aplica` → "—".
   - Botão "Exportar CSV".
   - Rodapé com período, fuso, critérios e "gerado em".
9. **Atualizações de documentação, na mesma entrega.**
   - **`metricas-e-indicadores.md`:**
     - Linha 21: "gravados na própria sessão" passa a "igreja da sessão; setor e regional vigentes da igreja (RN-09)".
     - Linhas 30 e 34: "quem não é da equipe do EBI" e "níveis acima" passam a "acima da igreja; a igreja e tudo abaixo dela veem números reais". Acrescentar a cascata e o rótulo "Não divulgado".
     - Linha 7 (M-01): registrar a atribuição pela igreja de referência e a exclusão de quem não tem igreja.
     - Seção de supressão: o M-09 não entra na supressão, porque conta igrejas e o valor de uma igreja é 0 ou 1; esconder o 1 revelaria o valor.
   - **`ADR-0020`:**
     - "acima do EBI" passa a "acima da igreja".
     - A exceção "a equipe do próprio EBI vê os números reais" é substituída por "a igreja e tudo abaixo dela veem números reais".
     - Acrescentar a cascata (decisão 6a) e que o M-09 fica fora da supressão, com o mesmo motivo.
     - **M-01:** o ADR diz que M-01 usa as definições de ativo dele. Passa a separar os dois: M-01 (cadastrados) é `status = ativo`; "participante ativo" (presença em 12 meses ou cadastro em 90 dias) é um indicador próprio.
   - **`matriz-de-autorizacao.md`:** nova linha `relatorio.ler` em "Operações implementadas".
   - **`endpoints.md`:** marcar `/reports/attendance` com `no OpenAPI` e remover `/reports/participants`.
   - **Linha 15 (M-09) de `metricas-e-indicadores.md`:** "EBI com status `ativo`" passa a "EBI com sessão realizada nos últimos 90 dias (ADR-0020)".
   - **`openapi.yaml`:** a resposta de `GET /participants` ganha `suprimido` e `motivo` (decisão 11).
10. **Exportação CSV.**
    - Feita pelo mesmo endpoint com `formato=csv`, com a mesma autorização, o mesmo cálculo e a mesma supressão. O arquivo **não contém nenhum número suprimido**.
    - **Uma linha por nó:** primeiro o nó aberto, depois cada filho direto.
    - **Colunas:** `nivel`, `id`, `nome`, os sete indicadores, `periodo_inicio`, `periodo_fim`, `fuso`, `gerado_em` e `criterios`. Assim a declaração da RF-14.6 vai em cada linha.
    - **`criterios`** é o mesmo texto do campo `criterios` do JSON e declara:
      - participantes sem igreja de referência não entram em cadastrados nem em ativos;
      - os status considerados: presença `valida`, sessão `encerrada` com `realizada`, participante `ativo`;
      - os indicadores do período (participantes distintos, presenças, sessões, média) e os da data de geração (cadastrados, ativos, igrejas com EBI ativo).
    - Células suprimidas trazem o texto `< 5` ou `Não divulgado`. `nao_se_aplica` fica vazio.
    - Campos de texto que começam com `=`, `+`, `-` ou `@` são neutralizados, contra injeção de fórmula em planilha (aprovado pelo produto).
    - A exportação é **auditada** (`relatorio.exportado`, com escopo, nó e período, sem nomes), seguindo o dicionário de dados ("auditoria de exportação"), com aprovação do produto. Não exige reautenticação: é só agregado, e o mecanismo de reautenticação ainda não existe no código.
    - **Exportação com nomes continua proibida (ADR-0029).**
11. **Lista de participantes com corte de 5 por igreja de referência (decisão do produto).** Fecha a dedução do M-01 por contagem da lista. O corte não pode ser pelo total da busca: 3 pessoas da igreja A e 6 da B somam 9, e as 3 de A seriam separadas pelo `igreja_referencia_id` que o `ParticipanteResource` devolve (`:27`, junto com `status`, `:25`).
    - **Onde fica:** a tela de Participantes consome `GET /participants` (`ParticipantController::index`). O endpoint busca por nome (`q`), com `data_nascimento` e `igreja_referencia_id` opcionais.
      - O `DetectorDuplicidade` filtra o nome **em PHP**, sem `limit()`.
      - O controller depois filtra pelo território, também em PHP.
      - A regra entra **no controller, depois do filtro territorial e antes de montar a resposta**. Aplicada no cliente, ela vazaria: a API já teria devolvido os nomes.
    - **Regra, aplicada ao resultado já filtrado pelo território:**
      - Os resultados são agrupados por `igreja_referencia_id`.
      - O tamanho de cada grupo conta só participantes `ativo`. O `DetectorDuplicidade` exclui `mesclado` (`:27`), mas devolve `inativo`, e inativos não podem engordar o grupo.
      - Grupo com 1 a 4 ativos, ou sem nenhum ativo: **todos** os participantes do grupo, ativos e inativos, saem da resposta.
      - Grupo com 5 ou mais ativos aparece inteiro, como hoje.
      - **Sem igreja de referência:** esses participantes sempre saem da resposta. É coerente com o relatório, em que eles não contam em nó nenhum. Por isso não formam grupo próprio.
    - **Resposta:** `200` com os grupos que passaram. Se algum participante foi retirado, a resposta traz `suprimido: true, motivo: "limiar"`, sem dizer quantos foram retirados nem de qual igreja. Se todos foram retirados, sai `{ data: [], suprimido: true, motivo: "limiar" }`. Zero resultados na busca é `data: []` sem supressão.
    - **Para quem vale:** o mesmo critério da decisão 6. Vale para quem atua acima da igreja. Na prática, entre os papéis com `participante.ler`, são `super_admin`, `adm_regional` e `adm_setor`, além de qualquer papel cujo contexto tenha `tipo_escopo` acima de `igreja`. Coordenadora, adm_igreja, Ministério Local e auxiliar com escopo `igreja`/`ebi` continuam vendo a lista. O auxiliar entra aqui porque tem `participante.ler` (`AutorizadorOperacao.php:80`) e atua na própria igreja, embora não tenha `relatorio.ler`. Os ministérios Central, Regional e Setorial não têm `participante.ler`, então já não veem a lista.
    - **Auditoria:** a leitura suprimida continua auditada com a finalidade, sem ids de participante.
    - **Fora da regra:** `GET /participants/{id}` (leitura por id) e a busca da presença em `GET /sessions/{id}/participants`. Essas leituras não fazem parte da lista e não mudam.
    - **Tela:** com `suprimido`, mostra "Participantes de igrejas com menos de 5 resultados não são exibidos no seu nível."

## Riscos

- **Desempenho:** a cascata exige calcular a subárvore inteira até os EBIs para decidir cada linha. No nível Central, isso é `DISTINCT` sobre um ano de presenças mais a lista de presenças dos últimos 12 meses, a cada clique. No piloto (uma regional) não é problema. Quando o relatório nacional passar de ~2 s, revisar índices (`presenca(sessao_id, status)`, `sessao(igreja_id, status, inicio_previsto)`, `participante(igreja_referencia_id, status)`) e, só depois, a abordagem B.
- **Perda de informação pela cascata (decisão 6a):** custo aceito pelo produto. Em regiões com muitas igrejas pequenas, setores, regionais e a Central podem ficar sem total visível.
- **Dedução comparando consultas: risco aceito no piloto.** A proteção vale dentro de uma mesma resposta. Comparar períodos sobrepostos (P∪Q − Q), ou consultas feitas antes e depois de um estorno, de uma contingência, de uma mesclagem ou de uma transferência, pode revelar um número suprimido. O produto aceitou manter o período livre. O risco deve ser levado ao DPO junto com o D-10.
- **Vínculos com papel incompatível com o escopo:** o cadastro de vínculos continua aceitando. O relatório não se expõe por causa da decisão 6, mas o vínculo segue existindo para as outras operações.
- **Participantes sem igreja de referência:** decisão aceita pelo produto. Ficam fora de M-01 e de ativos em todos os níveis, e o número nacional fica menor que o real.
- **Dedução do M-01 comparando antes e depois de um cadastro: risco aceito no piloto.** A decisão 11 fecha a dedução por contagem da lista, mas continua aberta a comparação do número de cadastrados antes e depois de um cadastro novo. Vai ao DPO junto com a dedução por consultas acima.
- **Busca de participante acima da igreja (decisão 11): custo aceito.** Uma busca por nome raramente acha 5 pessoas da mesma igreja. Na prática, `super_admin`, `adm_regional` e `adm_setor` deixam de encontrar pessoas na busca.
- **Mesclagem em cadeia e em ciclo (dívida conhecida):** o `GerenciadorMesclagem` aceita mesclar um principal anterior num terceiro e não rejeita principal já mesclado. O relatório se protege resolvendo a cadeia (decisão 5). A correção da mesclagem fica fora desta entrega. O mesmo vale para o snapshot sobrescrito numa segunda mesclagem.
- **Fuso por igreja:** se as igrejas tiverem fusos diferentes, o "dia" de cada sessão é diferente, e o relatório declara "fuso de cada igreja". No piloto de Brasília é um fuso só.

## Testes que provam o pronto

- **CA-14:** um participante presente em igrejas de **regionais diferentes** conta 1 em M-02 da Central e 2 em M-03. Repetir no setor e na regional.
- **CA-18:** transferir uma igreja entre setores e entre regionais.
  - Os números anteriores e o M-01 da igreja passam para o novo nó.
  - O perfil do território antigo perde acesso à igreja (404) e o do novo ganha.
- **Contagem, casos-limite:**
  - sessão realizada sem nenhuma presença entra em M-05 e reduz a média (protege contra INNER JOIN);
  - presenças de um duplicado mesclado contam 1 em M-02, e voltam a contar 2 depois de desfazer;
  - sessão às 23h30 do último dia, no fuso da igreja, entra no período; à 00h30 do dia seguinte, não;
  - duas igrejas com fusos diferentes;
  - estorno e sessão cancelada ou não realizada ficam fora; contingência entra.
- **M-01 e ativos:**
  - participante conta só na igreja de referência, mesmo com presença em outra;
  - sem igreja de referência, não conta em nenhum nó;
  - `inativo` e `mesclado` ficam fora;
  - ativo por presença há 11 meses (qualquer igreja) e por cadastro há 89 dias;
  - não ativo com presença há 13 meses e cadastro há 91 dias;
  - no nó EBI, `nao_se_aplica`.
- **M-09:** EBI com sessão realizada há 89 dias torna a igreja ativa; há 91 dias, não. Igreja com dois EBIs ativos conta 1. Para quem atua acima da igreja, uma igreja com M-09 = 1 mostra 1, sem `suprimido`, e um setor com M-09 de 1 a 4 mostra o número real.
- **Supressão:**
  - contagens de 1 a 4 voltam `null`, `suprimido`, `limiar` para os perfis acima da igreja;
  - coordenadora, adm_igreja e Ministério Local com escopo `igreja`/`ebi` recebem o número real;
  - um `ministerio_local` com escopo regional recebe supressão;
  - zero continua zero;
  - nenhum relatório acima da igreja mostra valor de pessoas ou sessões entre 1 e 4.
- **Cascata:**
  - Um EBI com 3 presenças dentro de uma igreja de um setor de uma regional:
    - a igreja aberta pelo setorial fica `filho_suprimido`;
    - a linha da igreja no setor aparece `filho_suprimido`, e os totais do setor também;
    - a linha do setor na regional aparece suprimida, e os totais da regional também;
    - o mesmo vale para a Central.
  - O mesmo nó tem a mesma situação aberto ou como linha do pai.
  - Sem nenhum descendente entre 1 e 4, todos os totais aparecem.
  - Quando os dois motivos se aplicam, prevalece `filho_suprimido`.
  - Um setor cujas igrejas têm todos os indicadores de pessoas e sessões ≥ 5 e M-09 = 1 em cada uma mostra todos os totais, e cada linha mostra M-09 = 1. Nada é suprimido.
- **Mesclagem em cadeia (decisão 5):**
  - B → A e depois A → C: presenças de A, B e C contam 1 em M-02, e o mapa entrega B → C e A → C;
  - desfazer A → C deixa A e C separados, com B ainda em A (2 em M-02);
  - um ciclo A → B → A conta 1 e não derruba o relatório.
- **Lista de participantes (decisão 11), para `adm_setor`:**
  - 3 da igreja A e 6 da B → aparecem só os 6 de B, com `suprimido: true`, sem nenhum id ou nome de A no corpo;
  - 3 ativos e 2 inativos da mesma igreja → o grupo inteiro sai;
  - participante sem igreja de referência nunca aparece, e a resposta traz `suprimido: true`;
  - todos os grupos retirados → `data: []`, `suprimido: true`;
  - zero resultados → `data: []` sem supressão;
  - coordenadora, adm_igreja e auxiliar com escopo `igreja`/`ebi` veem a lista com 1 a 4;
  - um papel de igreja com escopo regional recebe supressão;
  - participantes fora do território não contam para o limite.
- **Exportação:**
  - o CSV tem uma linha para o nó e uma por filho, com valores iguais aos do JSON;
  - não contém nenhum valor de 1 a 4 nem total escondido para quem atua acima da igreja;
  - a coluna `criterios` declara a exclusão de quem não tem igreja de referência, os status considerados e quais indicadores são do período e quais são da data de geração;
  - M-09 aparece com o valor real;
  - nome iniciado por `=` sai neutralizado;
  - gera o evento `relatorio.exportado`;
  - sem `relatorio.ler` → 403.
- **Autorização:**
  - papel sem `relatorio.ler` (auxiliar, responsável) → 403;
  - nó inexistente → 404;
  - acesso horizontal (outro setor do mesmo nível) → 404;
  - acesso vertical (setorial pedindo a regional) → 404;
  - `X-Acting-Scope` forjado ou de vínculo revogado → 403 `acting_scope_invalido`.

## Deixado ao construtor

- A forma exata do SQL, dos índices e dos contratos entre módulos (desde que nenhum leia tabela de outro).
- Os nomes das classes auxiliares.
- Reaproveitar a tabela de `Dependentes.tsx`.
- A mensagem de período vazio e o nome do arquivo CSV.
- Como a tela descobre o nó raiz (pelo `/me/scopes` ou pela resposta sem `nivel`).

## Leituras confirmadas pelo produto (alternativas descartadas)

- **Sem igreja de referência, não conta em nenhum nó.** Descartado: uma linha só na Central, porque uma linha pequena esconderia a Central inteira.
- **Atribuição só pela igreja de referência.** Descartado: atribuir por presença, ou por referência e presença, porque o participante contaria em várias igrejas e o M-01 deixaria de se somar entre filhos.
- **M-01 e ativos `nao_se_aplica` no EBI.** Descartado: copiar no EBI os números da igreja quando ela tem um EBI só, porque o resultado mudaria quando a igreja ganhasse um segundo EBI.
- **Presença em qualquer igreja torna o participante ativo.** Descartado: só presença no próprio nó.
- **Data de referência de "ativo" e M-09 = data de geração.** Descartado: o `fim` do período.
- **M-09 pelo ADR-0020 (sessão realizada em 90 dias).** Descartado: `status = ativo` do EBI, que contaria EBIs sem sessão.
- **M-09 nunca suprimido** (decisão 5). Descartado: aplicar o limiar só na linha, porque o M-09 de uma igreja é 0 ou 1 e esconder o 1 entrega o valor; e aplicar com cascata, que deixaria o relatório quase sem número.
- **Lista com corte por igreja de referência** (decisão 11). Descartado: corte pelo total da busca, que deixa separar uma igreja pequena pelo `igreja_referencia_id`.
