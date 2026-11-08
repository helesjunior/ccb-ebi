# RF-15 — Notificações

**Status:** proposto · **Épico:** [E12](../../07-produto/backlog-epicos.md) · **Fase:** 1 (e-mail transacional), 3 (push, WhatsApp)

## Requisitos
1. E-mail transacional para verificação, recuperação de conta e alertas de segurança.
2. Notificações de escala (nova, alterada, lembrete, troca), confirmação ou pedido de comprovação de vínculo, feedback liberado e avisos do EBI do dependente (sessão cancelada ou horário alterado); push opcional.
3. WhatsApp só por provedor/API oficial, com condições contratuais, custo, finalidade e permissões avaliados.
4. Preferências de comunicação e registros de envio/entrega, sem armazenar códigos em texto claro.
5. Aviso ao responsável antes do 12º aniversário do participante sem exceção inclusiva (proposto; ver [elegibilidade](../../02-regras-de-negocio/elegibilidade-etaria.md)).

## Relacionados
- Fluxos: [FL-01](../fluxos/FL-01-cadastro-responsavel-e-participante.md), [FL-04](../fluxos/FL-04-escala.md), [FL-05](../fluxos/FL-05-feedback.md)
- Critérios de aceite: [CA-24](../criterios-de-aceite.md#ca-24)
- Dados: `notificacao`, `preferencia_notificacao`
- API: a detalhar (preferências do usuário)
