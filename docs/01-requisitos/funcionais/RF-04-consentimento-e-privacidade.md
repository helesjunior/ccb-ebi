# RF-04 — Consentimento, transparência e privacidade

**Status:** proposto · **Épico:** [E05](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Apresentar aviso de privacidade e termo de consentimento específico, claro, destacado e compreensível antes do cadastro do participante.
2. Registrar versão do termo, finalidade, responsável declarante, data/hora, evidência de aceite, origem e status.
3. Permitir consultar e retirar consentimento e solicitar direitos do titular, com fluxo de análise e efeitos definidos por finalidade e base legal.
4. **Não presumir que consentimento é a única base legal.** O mapeamento de bases legais vai para avaliação jurídica/DPO. Ver [LGPD](../../05-seguranca-e-privacidade/lgpd.md).
5. Separar consentimentos facultativos (notificações não essenciais, fotos, uso de imagem) do tratamento necessário à operação.
6. Não publicar nome, foto, frequência ou feedback de participantes na biblioteca de atividades.

## Relacionados
- Regras: [RN-10](../../02-regras-de-negocio/regras-de-negocio.md#rn-10), [RN-12](../../02-regras-de-negocio/regras-de-negocio.md#rn-12)
- Critérios de aceite: [CA-16](../criterios-de-aceite.md#ca-16)
- Dados: `termo_versao`, `manifestacao_privacidade`
- API: `GET /privacy/consents`, `POST /privacy/consents`, `POST /privacy/requests`

## Pendências
- [D-04](../../06-arquitetura/decisoes/README.md): bases legais, termos, retenção, descarte e incidentes.
