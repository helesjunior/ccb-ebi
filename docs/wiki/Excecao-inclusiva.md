# Exceção inclusiva

O EBI atende uma **faixa etária**. Uma criança que não se encaixa na faixa pode ser aceita por
**exceção inclusiva** — mas a exceção não é informal: ela é pedida, decidida, registrada e tem
prazo.

## Por que existe

Sem exceção, o sistema recusaria a presença da criança e ela ficaria de fora do EBI. Com exceção,
a inclusão é uma **decisão registrada** da coordenação, com o responsável, e não uma brecha no
cadastro.

## Aviso dos 12 anos

Aos 12 anos, sem exceção aprovada vigente, a presença da criança fica bloqueada. O sistema avisa
30 dias antes.

**Coordenação** (coordenadora ou coordenadora chefe atuando no EBI): ao abrir o **Início**, aparece
o bloco "Fazem 12 anos nos próximos 30 dias", com nome, matrícula e data de cada criança, e o botão
**Exceção inclusiva**, que abre esta tela.

Entram na lista os participantes ativos cuja **igreja de referência** é a igreja do EBI e que não
têm exceção aprovada vigente na data. Uma exceção apenas pedida não tira a criança da lista.

A criança **sem igreja de referência** não aparece em nenhum Início. Se ela frequenta o seu EBI,
preencha a igreja dela ([Participantes](Participantes)). A criança de outra igreja aparece para a
coordenação de lá.

- Lista vazia: "Ninguém faz 12 anos nos próximos 30 dias."
- Falha: "Não foi possível carregar os aniversários.", e o resto do Início continua funcionando.

Abrir o Início registra a consulta na auditoria. O aviso aparece só na tela, sem e-mail.

**Responsável:** vê o aviso em **Meus dependentes**; veja
[Fluxo do responsável](Fluxo-do-responsavel).

## Pedir a exceção

Menu **Participantes → Exceção inclusiva**, cartão **Solicitar (responsável)**.

Apesar do nome, o responsável não tem esta tela no menu: ele pede à coordenação do EBI, que
registra o pedido aqui.

![Exceção inclusiva](images/22-excecao.png)

1. Informe o **ID do participante** (a criança).
2. Escolha o **tipo de necessidade** (ex.: `neurodivergencia`).
3. Descreva os **apoios necessários** (opcional) — o que a equipe precisa saber para acolher bem.
4. Toque em **Solicitar exceção**.

> **Cuidado com o texto.** Os **apoios necessários** descrevem *o que ajuda a criança a
> participar*, não um diagnóstico. "Precisa de um adulto por perto na hora do lanche" é útil;
> prontuário não é assunto do sistema.

## Decidir a exceção

No mesmo menu, cartão **Decidir (coordenadora)**:

1. Informe o **ID da exceção**.
2. Escolha a **decisão**: **Aprovar**, **Recusar**, **Suspender** ou **Revogar**.
3. Defina **Vigente até** (opcional): a exceção com prazo pede revisão depois.
4. Registre o **motivo** (opcional, mas recomendado em recusa e revogação).

| Decisão | Quando usar |
|---|---|
| **Aprovar** | A criança pode participar da faixa, conforme a solicitação |
| **Recusar** | Não é o caso; explique o motivo |
| **Suspender** | Valeu, mas foi interrompida temporariamente |
| **Revogar** | Deixa de valer em definitivo |

Você só decide exceções de crianças do seu EBI. Fora dele, aparece "Exceção não encontrada." e
nada muda. O Super Admin decide qualquer exceção.

A decisão é da **coordenação do EBI** — é ela quem conhece o dia a dia —, em conjunto com o
responsável. Não é uma fila de aprovação administrativa.

## O que a exceção não faz

- **Não** muda a data de nascimento nem a ficha da criança.
- **Não** estende automaticamente o EBI para outras crianças da mesma família.
- **Não** é permissão de presença: ela destrava o caso, mas a presença continua sendo registrada
  sessão a sessão.

## Auditoria

Pedido, decisão, prazo e motivo ficam na trilha de eventos. Em caso de questionamento, a resposta
está registrada.
