# ADR-0008 — Moderação de feedback pelo Ministério Local

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-05

## Contexto
RF-09 previa moderação de feedback, mas não dizia quem modera nem o que o responsável vê.

## Decisão
O responsável vê o feedback **somente depois de moderado pelo irmão do Ministério Local** da igreja responsável pelo EBI.

## Consequências
- Novo perfil **Ministério Local**, com escopo de igreja ([perfis](../../00-visao/perfis-e-atores.md)).
- O estado `em_revisao` do feedback é obrigatório; não há liberação direta pela coordenadora.
- Feedback pendente de moderação não aparece para o responsável.
- **A definir:** prazo de moderação e o que acontece se a igreja não tiver Ministério Local cadastrado no sistema (proposta: o feedback fica retido e o administrador da igreja é avisado).
