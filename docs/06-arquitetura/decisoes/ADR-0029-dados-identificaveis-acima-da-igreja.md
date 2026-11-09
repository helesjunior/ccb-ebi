# ADR-0029 — Acesso e exportação de dados identificáveis acima da igreja

**Status:** proposto pelo produto · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior (produto); aguarda CCB e DPO · **Resolve:** D-10 (em parte)

## Contexto
Setor, Regional e Central precisam de indicadores, mas não de nomes de crianças. O produto aprovou a regra mais restritiva abaixo para levar à CCB e ao DPO.

## Decisão proposta
1. Acima da igreja (Setor, Regional e Central), o acesso é **só a números agregados**, com a supressão de contagens pequenas do [ADR-0020](ADR-0020-supressao-e-definicao-de-ativo.md).
2. **Exportação com nomes desligada no MVP.**
3. Depois do MVP, acesso identificado acima da igreja só **por exceção**, com:
   - pessoa nomeada;
   - finalidade registrada;
   - prazo de até 30 dias;
   - aprovação do DPO;
   - registro em auditoria.

## Pendente com CCB e DPO
- Confirmar a regra e quem pode pedir a exceção.
- Quem exerce o papel de DPO na CCB para essa aprovação.

## Consequências
- O plano de desenvolvimento já parte dessa regra; as linhas `*` (finalidade registrada) da [matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) usam o mecanismo de exceção quando ele for implementado (E05).
