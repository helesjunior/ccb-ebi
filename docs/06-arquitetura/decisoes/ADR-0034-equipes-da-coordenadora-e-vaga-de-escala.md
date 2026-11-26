# ADR-0034 — Equipe da coordenadora e vaga de escala (Etapa 7, E07)

**Status:** aceito · **Data:** 25/11/2026 · **Aprovado por:** Heles Júnior (a confirmar) · **Resolve:** E07 (evolução da escala) · **Emenda:** [ADR-0033](ADR-0033-coordenadora-chefe-e-regras-da-escala.md) (§4, §5, §7) · **Relaciona:** ADR-0010, ADR-0017, ADR-0029

## Contexto
O ADR-0033 fechou a escala "do EBI": quem pode ser escalado é qualquer pessoa da equipe do EBI (`vinculos de acesso`), e a lista da equipe é uma só por EBI. Na prática cada coordenadora tem o seu time de auxiliares, com laços de confiança e disponibilidade próprios; a lista única obrigava a coordenadora a vasculhar gente de outros times e a montar a escala do zero toda sessão.

Faltava também o caminho para **faltas no dia**: alguém avisa que não vem, e a coordenação precisa reposição sem passar por convite manual de cada pessoa. O ADR-0033 não tem esse fluxo — só a troca entre membros já escalados (`troca.decidir`).

Esta ADR registra a correção do modelo: **cada coordenadora tem a sua equipe**, a escala nasce de uma delas e é mantida por ela; a falta abre uma **vaga**, que as equipes vizinhas veem e disputam, e **só a dona da escala escolhe**. O sistema ainda não está em produção: não há dados a preservar.

## Decisão

### 1. Cada coordenadora monta e mantém a própria equipe
- Tabelas novas `equipe` (dona = `coordenadora_id`; **uma vigente por coordenadora × EBI**) e `equipe_membro` (`equipe_id` × `usuario_id`, mais `incluido_por`). O quadro guarda a **intenção** da coordenadora, não a autorização: o papel de equipe de cada pessoa é lido **do vínculo de acesso vigente hoje** (`ConsultarEquipeEbi::papeisVigentesHoje`), então quem perde o vínculo sai da conta sozinho.
- **Uma coordenadora tem no máximo uma equipe vigente por EBI.** A equipe é criada pela própria coordenadora; `coordenadora_chefe` e `adm_igreja` criam em nome de qualquer coordenadora vigente hoje (a chefe herda a operação por ser superconjunto da coordenadora).
- **Os membros só a dona edita** (`403 nao_e_dona_da_equipe`); chefe e `adm_igreja` leem e supervisionam, mas não mexem na equipe alheia.
- Papel novo na matriz: `equipe.gerenciar` (coordenadora, coordenadora_chefe, adm_igreja). Um único gate cobre leitura e escrita da equipe; a regra de "só a dona" é do serviço.
- **Um auxiliar pode servir a várias coordenadoras** (1..N): não há exclusividade, e nenhuma equipe "rouba" o auxiliar de outra.
- Só entra na equipe quem tem papel de equipe vigente no EBI ou na igreja dele: `auxiliar` ou `coordenadora`; vínculo vencido (`sem_vinculo`) aparece na leitura mas não é escalável. Outro papel responde `422 membro_inelegivel`.

### 2. A escala nasce de uma equipe, e a equipe é a dona
- `escala.equipe_id` passa a ser **NOT NULL**: toda escala pertence a uma equipe, e a **dona da escala é a coordenadora dona da equipe** (`DonaDaEscala`: papéis `coordenadora` e `coordenadora_chefe`).
- Montar a escala = dona como `coordenadora` + os membros vigentes da equipe como `auxiliares`, numa chamada só. A dona precisa ter vínculo de coordenadora vigente hoje; sem vínculo, `409 dona_sem_vinculo`.
- **A dona não escreve na própria escala quando está atuando como auxiliar nela** (a matriz recusa `escala.gerenciar` ao auxiliar, antes do serviço).
- Chefe e `adm_igreja` **supervisionam** todas as operações da escala (`escala.gerenciar`, `equipe.ler` no detalhe, inclusão e retirada de membros, estimativa, presença). A única operação que **não** é deles é escolher quem preenche a vaga.

### 3. A escala cresce no dia — sem perder a aprovação
- **Acréscimo (incluir membro)** preserva a escala aprovada: `status`, `aprovado_por`, `aprovado_em` e `participantes_por_auxiliar` ficam como estão, e as trocas pendentes continuam pendentes.
- **Retirar membro, mudar a função ou a estimativa** reverte a escala para `prevista`, copia o divisor vigente do EBI para a escala e **cancela as trocas pendentes** (`escala.troca_cancelada`): a aprovação anterior deixou de valer.
- Pode-se escalar, no dia, **qualquer pessoa da equipe do EBI** — inclusive uma coordenadora atuando como auxiliar.
- `escala.equipe_id` é imutável depois de criada: a dona não muda de equipe no meio do caminho (trocar a dona fica no backlog).

### 4. Falta abre vaga; a dona escolhe; a chefe só é avisada
- Tabelas `vaga_escala` (`aberta`/`preenchida`/`cancelada`; `acrescimo` ou `substituicao`, com `substitui_membro_id` e o snapshot `substituido_usuario_id`) e `oferta_vaga_escala` (`pendente`/`aceita`/`retirada`/`descartada`, com `membro_criado_id` ligando a oferta aceita ao membro criado).
- **Abrir e cancelar vaga é `escala.gerenciar`** (`garantirGerir`): a dona e a supervisão (`coordenadora_chefe`, `adm_igreja`) do EBI, como o resto da escala. **Escolher** é só da dona. Só sessão `prevista`/`aberta` aceita vaga (`409 sessao_em_estado_invalido`); `substitui_membro_id` tem de ser auxiliar ativo da escala (`422 membro_invalido`), sem vaga aberta (`409 vaga_existente`).
- **A vaga aberta é visível a todos os auxiliares do EBI** (e às coordenadoras): a lista `GET /me/schedule-vacancies` não exige `escala.ler`, recorta pelos vínculos de equipe vigentes hoje e exclui as escalas em que a pessoa já está. Ela pode se oferecer (`POST /schedule-vacancies/{id}/offers`): precisa de papel de equipe vigente no EBI/igreja da sessão (senão 404), estar fora da escala (`422 membro_ja_escalado`), ser elegível na data da sessão (`422 membro_inelegivel`) e não ter oferta pendente (`409 oferta_existente`).
- **Só a dona escolhe** (`DonaDaEscala::podeEscolherOferta`); a pessoa escolhida entra como **`confirmado`**, sem nova confirmação, e a vaga vira `preenchida`. Para substituição, o membro original passa a `substituido`; as demais ofertas ficam `descartadas`. O `oferta_id` enviado tem de ser oferta **desta** vaga (`422 oferta_invalida`), para que a autorização da vaga da URL não sirva para mexer em oferta de outro EBI.
- **Quem vê os nomes (`podeGerir`)**: a dona e a supervisão (`coordenadora_chefe`, `adm_igreja`) **que alcançam a igreja da escala**. A chefe ou o `adm_igreja` de outro território recebe só o cartão público — e no cartão a projeção de gestão exige, para a supervisione, o escopo territorial; para a dona, o vínculo de coordenadora vigente hoje (dona sem vínculo hoje volta a ver só o cartão público).
- **Quem foi escolhido não é sigilo:** a vaga preenchida é fato do EBI, então o cartão de leitura (supervisão, `ministerio_local`) mostra a contagem e o `escolhido_nome`. O que nunca sai é *quem se ofereceu e não foi escolhida*.
- **A chefe não aprova vaga.** Ela é notificada pelo painel do EBI ("Vagas do EBI") e enxerga a vaga preenchida e quem foi escolhida; escolher não é dela (`403 nao_e_dona_da_escala`).
- A oferta retirada pelo próprio dono vira `retirada`; oferta alheia responde 404 e oferta já decidida `409 oferta_ja_decidida`.
- **Autorização atual é `fim` nulo**, sem "dura até a data": quem foi revogado hoje já não aparece em `papeisVigentesHoje` e, por isso, não oferece (404, sem revelar a vaga) nem é escolhido (`422 membro_inelegivel`, nada gravado). A elegibilidade pela data da sessão (`AvaliadorEquipe::vigenteEm`) é checada na oferta e na escolha; com `fim` nulo ela coincide com a atual, e é a regra que vale se o modelo de vínculo ganhar fim futuro.
- **A dona vê todas as ofertas** (pendentes, retiradas e descartadas) e o nome de quem é substituído; a supervisão e `ministerio_local` veem contagem + quem foi escolhido; quem entra só pela exceção de membro da escala recebe a projeção pública.

### 5. Privacidade da vaga: contagem para a supervisão, nomes só para a dona
- A leitura do cartão tem três projeções (`ProjecaoVaga`), decididas no controller:
  - **Pública** (quem não lê a escala, inclusive o auxiliar que se oferece): o cartão base, a **própria** oferta (`minha_oferta`) e os **próprios** conflitos.
  - **Leitor** (`escala.ler` no EBI: `ministerio_local`, `adm_regional`, `adm_setor`, `super_admin`, a chefe que não é a dona): o cartão base, a **contagem** de ofertas pendentes, o motivo de encerramento e o nome do substituído — **nunca** a lista de quem se ofereceu nem o nome de quem não foi escolhida.
  - **Gestão** (a dona): tudo o que o leitor vê, mais `escolhido_nome` e a lista `ofertas[]` com `id`, `usuario_id`, `nome`, `status` e `conflitos`.
- O nome de quem se ofereceu e não foi escolhida **não** chega ao `ministerio_local` nem a nenhum administrador acima da igreja. A tela de gestão do EBI mostra somente a contagem e quem foi escolhida.
- O texto livre da vaga (observação) é **visível a todos os auxiliares do EBI**: a tela avisa antes de abrir e pede para não escrever dados de saúde nem outros dados sensíveis.

### 6. Concorrência: escala primeiro, vaga depois
- Toda escrita da vaga passa pela `TravaEscala`, **na ordem escala → vaga**: `executarPelaVaga` lê o `escala_id` da vaga sem lock e então trava a escala. A ordem inversa (vaga, depois escala) gerou deadlock real (`40P01`) contra `retirarMembro`, que trava a escala e cancela as vagas abertas do membro.
- Os testes de concorrência (`tests/Concorrencia`) rodam contra PostgreSQL (`ebi_teste`), com processos paralelos: aceite × cancelamento, aceite × aprovação de troca do original, aceite × retirada do original, aceite × inclusão manual da mesma pessoa e dois aceites da mesma vaga. O `lockForUpdate` não tem efeito no SQLite dos testes comuns, por isso eles só rodam com `TESTE_PGSQL=1`.
- `409` depois do commit: o resultado devolve `ResultadoVaga::indisponivel()` e a exceção é lançada fora da trava; a equipe grava em transação própria.

### 7. Duas regras de conflito na oferta
- A oferta mantém a elegibilidade da sessão (`garantirElegivelNaData`, `422 membro_inelegivel`: faixa etária, papel de equipe vigente) e ganha `422 membro_ja_escalado` quando a pessoa já está naquela escala.
- **Conflito de horário é o único ponto que bloqueia:** oferecer em conflito com outra escala responde `422 conflito_de_horario` com os conflitos, e escolher revalida (a pessoa pode ter ganhado conflito depois de se oferecer — mesmo 422, nada gravado, oferta segue `pendente`). Fora do fluxo de vaga o conflito continua **só informativo** (montagem, aprovação e confirmação, ADR-0033 §6 e CA-12): quem decide é a dona. Ofertas pendentes não contam como conflito umas das outras. A corrida entre escalas diferentes (a mesma pessoa escolhida em duas vagas sobrepostas ao mesmo tempo) é **risco aceito**: a trava é por escala e o conflito aparece depois como aviso nas duas.
- A lista `minhasVagas` exclui as escalas em que a pessoa já é membro (qualquer status exceto `substituido`).

## Alternativas descartadas
- **Manter uma equipe única por EBI (ADR-0033)**: obrigava a coordenadora a escolher em toda gente do EBI e não modelava os times reais. Descartada.
- **Exclusividade do auxiliar por coordenadora**: simplificaria a consulta, mas uma auxiliar não poderia ajudar duas coordenadoras. Descartada — 1..N.
- **A chefe aprova quem preenche a vaga**: dobra o caminho de uma falta urgente e tira da dona o que é dela. A chefe supervisiona e é avisada; não aprova.
- **Chefe e `adm_igreja` também escolhem**: mesma razão; a escolha exige a visão da própria escala e do próprio time. Ficam com `403 nao_e_dona_da_escala`.
- **Abrir a vaga automaticamente quando o membro pede troca**: o fluxo de troca (`troca.decidir`) continua existindo entre membros; a vaga é para falta e para crescimento. Descartada a fusão.
- **Mostrar a lista de ofertas para a supervisão "só para acompanhar"**: expõe quem se candidatou e não foi escolhida a quem não precisa. Descartada — contagem.
- **Travar a vaga e depois a escala**: deadlock com a retirada de membro. Descartada.
- **Migrar dados de escalas existentes**: o sistema não está em produção; `escala.equipe_id` passou a NOT NULL editando a migration original. Descartada a migration de dados.

## Consequências
- Montar a escala ficou um clique: a dona e os membros vigentes entram juntos, e a escala nasce com a equipe registrada como dona.
- A falta tem caminho: a vaga aparece para todo o EBI, as equipes vizinhas disputam e a dona decide sem passar por aprovação.
- A tela `Equipes de escala` (`equipes-escala`) entra no grupo *Participantes* para coordenadora, chefe e `adm_igreja`; quem não gere vê em modo leitura.
- O ADR-0033 §4 ganha a exceção do acréscimo (o divisor **não** é recopiado nem a aprovação cai); §5 passa a cancelar só as trocas **pendentes**; §7 alinha o "cumprido" ao `RelatorioPresenca` — o M-06 mede sessões com escala aprovada e equipe mínima confirmada contra as sessões previstas, em %, e não presentes ÷ planejado.
- Eventos de auditoria novos: `equipe.criada`, `equipe.membro_incluido`, `equipe.membro_retirado`, `escala.vaga_aberta`, `escala.vaga_oferta`, `escala.vaga_oferta_retirada`, `escala.vaga_preenchida`, `escala.vaga_cancelada` (sem nomes de quem não foi escolhida).
- Sem produção, a `equipe.gerenciar` e a tabela nova entram sem script de dados; a chefe herda a operação por superconjunto.
- O backend e o cliente dependem da ordem escala → vaga; qualquer escrita nova em vaga que precise das duas tabelas tem de usar `executarPelaVaga`.

## Backlog
- **(a) Coordenadora falta com a sessão aberta:** retirar a dona da escala ou deixar a dona `sem_vinculo` hoje não transfere a escala; a dona continua quem é, e escolher a oferta fica travado (`409 dona_sem_vinculo` só impede abrir vaga). Falta o caminho de "quem assume a escala".
- **(b) Reforço depois do encerramento:** a vaga só existe em sessão `prevista`/`aberta`; não há como incluir gente numa sessão já encerrada (nem para corrigir a presença da equipe).
- **(c) Transferir a dona:** `escala.equipe_id` é imutável; mudar de equipe/coordenadora no meio exige decisão sobre o que acontece com vagas e ofertas abertas.
- **(d) Indicador de "nova atividade" para a chefe:** a chefe vê o painel de vagas e as trocas, mas nada avisa que apareceu vaga ou oferta nova; ela precisa abrir a tela.
- **(e) Notificação externa (E12):** quem se ofereceu não recebe aviso quando a vaga é preenchida, e a dona não recebe aviso quando alguém se oferece. Fica para o eixo de notificações.
