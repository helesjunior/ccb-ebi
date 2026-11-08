# RF-06 — Agenda e sessões

**Status:** proposto · **Épico:** [E04](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Cadastrar dias da semana, horários, exceções, feriados, cancelamentos e vigência da programação de cada EBI.
2. Gerar sessões previstas e permitir abertura, edição controlada, encerramento e cancelamento.
3. Cada sessão tem ID, EBI, unidade territorial na data (igreja, setor, regional), início/fim previsto e real, equipe designada, tema/passagem bíblica e situação.
4. Sessão encerrada só pode ser corrigida por perfil autorizado, com motivo e trilha de auditoria.
5. Impedir sessões duplicadas no mesmo EBI e intervalo, salvo exceção autorizada.
6. Ao encerrar, confirmar se a sessão foi realizada. Só sessões realizadas contam em [M-05](../../02-regras-de-negocio/metricas-e-indicadores.md).

## Relacionados
- Critérios de aceite: [CA-23](../criterios-de-aceite.md#ca-23)
- Regras: [RN-02](../../02-regras-de-negocio/regras-de-negocio.md#rn-02), [RN-09](../../02-regras-de-negocio/regras-de-negocio.md#rn-09)
- Dados: `agenda_ebi`, `agenda_excecao`, `sessao` ([estados](../../03-dados/estados.md#sessão))
- API: `GET /ebis/{id}/sessions`, `POST /sessions/{id}/open`, `POST /sessions/{id}/close`, `POST /sessions/{id}/cancel`
