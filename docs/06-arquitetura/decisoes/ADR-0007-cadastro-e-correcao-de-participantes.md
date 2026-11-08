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
- **Proposta a confirmar:** a coordenadora só mescla ou corrige participantes que tenham presença ou igreja de referência em EBI onde ela atua. Sem essa restrição, qualquer coordenadora alteraria cadastros de todo o país.
- A matriz de autorização foi atualizada.
