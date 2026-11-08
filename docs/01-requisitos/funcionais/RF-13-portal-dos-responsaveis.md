# RF-13 — Portal e aplicativo dos responsáveis

**Status:** proposto · **Épico:** [E11](../../07-produto/backlog-epicos.md) · **Fase:** 1 (Web), 3 (app)

## Requisitos
1. Painel por dependente com identificação, matrícula, histórico de presenças por data e igreja, passagem bíblica/tema da sessão e feedbacks liberados.
2. Atualizar dados permitidos e gerenciar solicitações de vínculo, termos, notificações e privacidade.
3. Ver informações da sessão mesmo quando o participante esteve em igreja diferente da de referência.
4. Não exibir nomes de outros participantes, escala nominal da equipe além do necessário, nem informações administrativas internas.

## Relacionados
- Regras: [RN-05](../../02-regras-de-negocio/regras-de-negocio.md#rn-05)
- Critérios de aceite: [CA-04](../criterios-de-aceite.md#ca-04), [CA-11](../criterios-de-aceite.md#ca-11)
- API: `GET /me/participants`, `GET /me/participants/{id}/attendance`
