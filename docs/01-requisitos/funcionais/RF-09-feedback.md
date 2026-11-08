# RF-09 — Feedback individual da participação

**Status:** proposto · **Épico:** [E10](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Coordenadora ou auxiliar autorizada pode registrar observações pedagógicas objetivas, respeitosas e pertinentes à participação na sessão.
2. Campos: participação (opcional, escala de 3 níveis: "participou com apoio", "participou", "participou e contribuiu" — proposto), atividade realizada, observação breve (até 500 caracteres, proposto), autor, data e visibilidade.
3. Proibidos: rótulos diagnósticos, avaliações vexatórias, dados sensíveis desnecessários e comparações públicas entre participantes.
4. Moderação antes de chegar ao responsável, conforme [ADR-0008](../../06-arquitetura/decisoes/ADR-0008-moderacao-de-feedback.md); correção e contestação auditadas.
5. Responsáveis veem apenas feedbacks liberados dos próprios dependentes. Registros internos de proteção/salvaguarda, se existirem, ficam em processo separado e não fazem parte do feedback comum.

## Relacionados
- Regras: [RN-10](../../02-regras-de-negocio/regras-de-negocio.md#rn-10)
- Fluxos: [FL-05](../fluxos/FL-05-feedback.md)
- Critérios de aceite: [CA-11](../criterios-de-aceite.md#ca-11)
- Dados: `feedback` ([estados](../../03-dados/estados.md#feedback))
- API: `POST /sessions/{id}/feedback`, `/feedback/{id}/moderation`

## Pendências
- Prazo de moderação e tratamento de igrejas sem Ministério Local cadastrado ([ADR-0008](../../06-arquitetura/decisoes/ADR-0008-moderacao-de-feedback.md)).
