# ADR-0007 — Quem cria, mescla e corrige cadastros de participantes

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-02

## Contexto
A especificação não dizia quem cria, aprova, mescla e corrige cadastros. Isso define as permissões de RF-02 e o tratamento de duplicidades.

## Decisão
- O **responsável cria** o cadastro do participante (FL-01).
- A **coordenadora** também pode criar, no cadastro assistido ([ADR-0015](ADR-0015-cadastro-assistido.md)).
- A **coordenadora mescla e corrige** cadastros, inclusive duplicidades detectadas pelo sistema.

## Consequências
- Mesclagem continua sem fusão automática irreversível: a coordenadora revisa, a operação é auditada e pode ser desfeita.
- Escopo: a coordenadora só mescla ou corrige participantes ligados aos EBIs onde atua; o resto vai para a coordenadora chefe ([ADR-0027](ADR-0027-escopo-de-mesclagem.md)).
- A matriz de autorização foi atualizada.
