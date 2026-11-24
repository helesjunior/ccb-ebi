# ADR-0033 — Coordenadora chefe e regras da escala (Etapa 7, E07)

**Status:** aceito · **Data:** 24/11/2026 · **Aprovado por:** Heles Júnior (a confirmar) · **Resolve:** E07; atualiza ADR-0010; relaciona ADR-0002, ADR-0017, ADR-0022, ADR-0029

## Contexto
O ADR-0010 fixou a equipe mínima e "a escala é aprovada pela Coordenadora chefe do EBI", mas a Etapa 2 nunca criou o campo `ebis.coordenadora_chefe_id` nem o papel. Até esta entrega não havia escala por sessão, aprovação, confirmação, troca nem o planejado × cumprido. Esta ADR registra as decisões da E07 e as que desviam do texto do ADR-0010.

## Decisão

### 1. Papel `coordenadora_chefe` e a unicidade por EBI
- Papel novo no `PapelSeeder`, superconjunto da `coordenadora`: todas as operações dela na `MATRIZ`, mais `escala.aprovar` e `troca.decidir`. Autoridade 35 (acima da coordenadora, abaixo de `adm_igreja`); só o `adm_igreja` e acima nomeiam a chefe.
- **Só escopo `ebi`**: convite ou vínculo com `coordenadora_chefe` em outro tipo de escopo responde `422 escopo_invalido_para_papel`.
- **Uma chefe vigente por EBI.** O recurso comum é `vinculo_acesso.chefe_de_ebi_id` (nullable, `UNIQUE`), preenchido com o id do EBI enquanto o vínculo está vigente e zerado ao encerrar. A regra vale nos três caminhos (`GerenciadorVinculo::conceder`, `AceitadorConvite::aceitar` e a criação do convite), que respondem `422 chefe_ja_definida`; a violação do índice numa corrida é traduzida no mesmo erro. Trocar de chefe = revogar a atual e conceder a nova.
- Onde a coordenadora já é reconhecida, a chefe também é: `AutoridadeDePapel`, `VisibilidadeIgreja::PAPEIS`, relatório, menus e telas.

### 2. Quem aprova: a chefe vigente, com fallback para o `adm_igreja`
- A escala e as trocas são aprovadas pela **coordenadora chefe vigente do EBI**. Sem chefe vigente, o **`adm_igreja` da igreja do EBI** aprova no lugar (desvio ao ADR-0010, que não previa fallback).
- **Troca pedida pela própria chefe** é decidida pelo `adm_igreja`: quem pede não decide o próprio pedido (`403 sem_permissao`), e o `adm_igreja` só decide o pedido da chefe (ou quando não há chefe vigente).
- **Escala em que a chefe está escalada:** ela aprova. Aprovar não é um pedido dela, e a equipe mínima é conferida pelo servidor.
- O servidor decide o aprovador a cada pedido (não grava "aprovador designado"). `super_admin` não aprova escala: não tem `escala.aprovar` na matriz.

### 3. Estimativa digitada pela coordenadora
- `estimativa_participantes` é obrigatória ao criar a escala (inteiro ≥ 0), digitada; não é calculada pela média das últimas sessões (a proposta do ADR-0010 fica de fora).
- `auxiliares_necessarios = ⌈estimativa ÷ participantes_por_auxiliar⌉` e `coordenadoras_necessarias = 1`. **Estimativa 0 → 0 auxiliares; o mínimo é só a coordenadora**, que é a fórmula literal do ADR-0010 e a mesma da `CalculadoraEquipeMinima`.

### 4. Divisor registrado na escala
- O `divisor_de_participantes` do EBI é **copiado para a escala na criação** e não muda depois. Mudar o divisor do EBI **não reescreve** escalas já criadas: o que vale é o número gravado.
- O cálculo usa o divisor gravado; a escala aprovada mostra o mínimo que foi conferido.

### 5. Alterar a escala depois de aprovada
- A escala `aprovada` **volta a `prevista`** quando a coordenadora altera a equipe, a estimativa ou os participantes; a aprovação anterior deixa de valer e o mínimo é conferido de novo.
- **As trocas abertas e aceitas daquela escala são canceladas** na mesma operação (`situacao = cancelada`), para ninguém ser aprovado por um pedido que a nova equipe desfez. Trocas já decididas (`recusada`) ficam como estão.
- Só a `coordenadora_chefe` (ou o `adm_igreja`, no fallback) aprova de novo; quem alterou não aprova sozinha se não for ela.

### 6. Conflito de agenda não bloqueia
- Escalar alguém que já está em outra escala na mesma sessão (ou na mesma hora) **não bloqueia**: o conflito é reportado na leitura da escala e na lista de minhas escalas, sem impedir a gravação.
- A `EscalaVisao` recorta o conflito para quem lê (a pessoa vê o próprio, a coordenação vê o da equipe do seu escopo); o motivo aparece como texto, e a decisão fica com quem escala.

### 7. Planejado × cumprido e o indicador M-06
- O **planejado** é a escala aprovada (participantes, equipe, estimativa) e o **cumprido** é o que a sessão registrou (presentes, equipe efetiva). O relatório compara os dois por sessão e por EBI sem gravar uma cópia do cumprido.
- **M-06 (cobertura da escala)** = cumprido ÷ planejado, com 1 decimal. É **sempre visível**: não entra em supressão por célula pequena e sai como `null` — exibido "—" — quando não há planejado, em vez de número inventado. O indicador não substitui o detalhamento da sessão.

## Consequências
- O ADR-0010 perde a promessa de `ebis.coordenadora_chefe_id` e da estimativa calculada; a unicidade passa para `vinculo_acesso.chefe_de_ebi_id` e a estimativa é digitada.
- Sem chefe vigente o EBI não fica travado: o `adm_igreja` aprova. O custo é precisar de um `adm_igreja` na igreja do EBI.
- Mudar o divisor do EBI não afeta escalas antigas — o que se lê na escala é o que foi conferido na aprovação.
- "Aprovar" no cliente é só conveniência: a trava de verdade é a `TravaEscala` no servidor, e o cliente não conhece o fallback.

## Dívidas e pendências
- O E07 não notifica a chefe nem o `adm_igreja` sobre escala pendente ou troca a decidir; quem aprova precisa abrir a lista.
- O conflito de agenda é informativo; não existe bloqueio opcional nem regra de "não pode ter duas escalas no mesmo horário".
- A escala não tem cancelamento nem cópia de uma sessão para outra; cada sessão é escalada à mão.




