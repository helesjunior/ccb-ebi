# ADR-0001 — Registrar decisões em ADRs

**Status:** aceito · **Data:** 08/11/2026

## Contexto
A especificação tinha 12 decisões pendentes (D-01 a D-12) listadas no fim do documento, sem registro de quem decidiu, quando e por quê. Sem esse registro, decisões são reabertas e o motivo se perde.

## Decisão
Cada decisão relevante de produto, arquitetura, segurança ou privacidade vira um arquivo `ADR-nnnn-titulo.md` nesta pasta. Decisões ainda em aberto ficam no [índice](README.md) como D-nn até serem resolvidas.

## Consequências
- O histórico das decisões fica versionado com o código.
- Mudar uma decisão aceita exige um novo ADR que substitui o anterior; o antigo fica com status `substituído por ADR-nnnn`.

## Modelo

```markdown
# ADR-nnnn — Título curto

**Status:** proposto | aceito | substituído por ADR-nnnn · **Data:** dd/mm/aaaa · **Resolve:** D-nn (se houver)

## Contexto
O problema e as restrições.

## Opções consideradas
1. Opção A — prós e contras.
2. Opção B — prós e contras.

## Decisão
O que foi decidido e quem aprovou.

## Consequências
O que muda e o que passa a ser necessário.
```
