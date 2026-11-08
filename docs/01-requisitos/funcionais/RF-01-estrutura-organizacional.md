# RF-01 — Estrutura organizacional

**Status:** proposto · **Épico:** [E02](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Cadastrar e manter Central (Brás), Regionais, Setores, Igrejas e EBIs, com o ministério de cada nível.
2. Cada igreja tem identificador estável, nome, endereço, fuso horário, latitude, longitude, situação e vinculação hierárquica.
3. Uma igreja pode não ter EBI ou ter mais de um EBI (raro hoje; possível na Central, no Brás).
4. Transferências de igreja entre setores e regionais ficam em histórico com vigência. Registros antigos não são reescritos.
5. Importação controlada e deduplicação de cadastros organizacionais.

## Relacionados
- Regras: [RN-09](../../02-regras-de-negocio/regras-de-negocio.md#rn-09)
- Critérios de aceite: [CA-18](../criterios-de-aceite.md#ca-18)
- Dados: `central_nacional`, `regional`, `setor`, `igreja`, `igreja_vinculacao`, `ebi` ([modelo](../../03-dados/modelo-conceitual.md))
- API: `GET /regions/{id}/sectors`, `/churches`, `/ebis` ([endpoints](../../04-api/endpoints.md))

## Decisões
- Hierarquia e ministérios por nível: [ADR-0017](../../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md).
