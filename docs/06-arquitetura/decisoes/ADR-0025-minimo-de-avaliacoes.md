# ADR-0025 — Mínimo de avaliações para exibir a média

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-20

## Decisão
A média de uma atividade só aparece a partir de **5 avaliações**. Abaixo disso, a tela mostra "poucas avaliações" com a quantidade (por exemplo, "poucas avaliações (3)"), e a atividade não entra na ordenação por avaliação. O valor 5 é um parâmetro configurável.

## Consequências
- Evita que uma única nota defina a média e reduz a chance de identificar quem avaliou.
- Critério de aceite em [CA-10](../../01-requisitos/criterios-de-aceite.md#ca-10).
