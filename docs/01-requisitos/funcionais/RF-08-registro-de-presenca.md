# RF-08 — Registro de presença

**Status:** proposto · **Épico:** [E04](../../07-produto/backlog-epicos.md) · **Fase:** 1 (geolocalização na fase 3)

## Requisitos
1. Na sessão aberta, pesquisar participante por nome ou matrícula, selecionar, confirmar identidade e verificar automaticamente a [elegibilidade etária](../../02-regras-de-negocio/elegibilidade-etaria.md) antes do lançamento.
2. Registrar presença com participante, sessão, data/hora, usuário que registrou, origem (Web/app) e status. Igreja e EBI vêm da sessão.
3. Um participante pode frequentar igrejas diferentes, com matrícula única e histórico por local.
4. Impedir presença duplicada na mesma sessão. Sinalizar presença em sessões simultâneas de locais diferentes para verificação.
5. Correção ou estorno justificado e auditado, sem exclusão física silenciosa.
6. **Geolocalização (fase 3):** com permissão do dispositivo, sugerir igrejas próximas pelas coordenadas cadastradas; exigir confirmação manual do EBI e da sessão. Seleção manual sempre disponível. Proximidade não é prova de presença; participantes não são rastreados.
7. Operação com conectividade instável e sincronização segura é evolução priorizável ([E13](../../07-produto/backlog-epicos.md)); conflitos e duplicações são resolvidos no servidor.

## Relacionados
- Regras: [RN-02](../../02-regras-de-negocio/regras-de-negocio.md#rn-02), [RN-03](../../02-regras-de-negocio/regras-de-negocio.md#rn-03), [RN-04](../../02-regras-de-negocio/regras-de-negocio.md#rn-04), [RN-10](../../02-regras-de-negocio/regras-de-negocio.md#rn-10), [RN-13 a RN-16](../../02-regras-de-negocio/elegibilidade-etaria.md)
- Fluxos: [FL-02](../fluxos/FL-02-presenca-em-outra-igreja.md)
- Critérios de aceite: [CA-02](../criterios-de-aceite.md#ca-02), [CA-05](../criterios-de-aceite.md#ca-05), [CA-07](../criterios-de-aceite.md#ca-07), [CA-13](../criterios-de-aceite.md#ca-13), [CA-17](../criterios-de-aceite.md#ca-17)
- Dados: `presenca` ([estados](../../03-dados/estados.md#presença))
- API: `POST /sessions/{id}/attendance`, `POST /attendance/{id}/reversal`, `GET /churches/nearby`

## Pendências
- [D-08](../../06-arquitetura/decisoes/README.md): funcionamento offline e sincronização.
