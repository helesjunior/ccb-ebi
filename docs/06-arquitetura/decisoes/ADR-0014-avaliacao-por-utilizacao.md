# ADR-0014 — Avaliação de atividade por utilização

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-16

## Decisão
Mantida a regra original: **uma avaliação ativa por usuário e utilização** (RF-12).

## Consequências
- Quem usa a mesma atividade em várias sessões pode avaliá-la várias vezes, e esse uso repetido pesa mais na média. Isso é aceito: reflete a experiência de cada sessão.
- A unicidade no banco fica em `avaliacao_atividade(utilizacao_id, usuario_id)`.
