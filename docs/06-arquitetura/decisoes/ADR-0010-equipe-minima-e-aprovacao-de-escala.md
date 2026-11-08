# ADR-0010 — Equipe mínima e aprovação de escalas

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-09 · **Atualizado:** 08/11/2026, com o tamanho da equipe

## Contexto
RF-07 e a métrica M-06 dependiam de uma regra de equipe mínima e de quem aprova escalas.

## Decisão
- Toda sessão precisa de **equipe mínima**: **1 coordenadora + 1 auxiliar para cada 4 participantes** (estimativa).
- Fórmula: auxiliares = ⌈participantes esperados ÷ 4⌉. Exemplos: 1 a 4 crianças → 1 auxiliar; 5 a 8 → 2; 9 a 12 → 3.
- A escala é **aprovada pela Coordenadora chefe** do EBI.

## Consequências
- Nova atribuição **Coordenadora chefe**, uma por EBI ([perfis](../../00-visao/perfis-e-atores.md)).
- A escala ganha o estado `aprovada`; só escala aprovada conta em M-06.
- Como o número de crianças só é conhecido no dia, a escala é planejada com uma **estimativa**: a média de presentes nas últimas 4 sessões do EBI (proposta). A coordenadora pode ajustar a estimativa.
- Durante a sessão, se as presenças passarem do que a equipe escalada cobre, o app avisa a coordenadora. O aviso não bloqueia o registro de presença.
- A proporção (4 participantes por auxiliar) fica como parâmetro configurável, porque é uma estimativa.
