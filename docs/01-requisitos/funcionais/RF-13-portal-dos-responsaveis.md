# RF-13 — Portal e visão dos responsáveis no app

**Status:** proposto · **Épico:** [E11](../../07-produto/backlog-epicos.md) · **Fase:** 1 (Web), 3 (app)

## Requisitos
1. A visão dos responsáveis fica no **app único** de todos os perfis, além do portal Web ([ADR-0009](../../06-arquitetura/decisoes/ADR-0009-aplicativo-unico.md)).
2. Painel por dependente com identificação, matrícula, histórico de presenças por data e igreja, passagem bíblica/tema da sessão e feedbacks liberados.
3. Ver apenas feedbacks moderados pelo Ministério Local e liberados.
4. Atualizar dados permitidos e gerenciar solicitações de vínculo, termos, notificações e privacidade.
5. Ver informações da sessão mesmo quando o participante esteve em igreja diferente da de referência.
6. Não exibir nomes de outros participantes, escala nominal da equipe além do necessário, nem informações administrativas internas.

## Relacionados
- Regras: [RN-05](../../02-regras-de-negocio/regras-de-negocio.md#rn-05)
- Fluxos: [FL-01](../fluxos/FL-01-cadastro-responsavel-e-participante.md), [FL-02](../fluxos/FL-02-presenca-em-outra-igreja.md), [FL-05](../fluxos/FL-05-feedback.md)
- Permissões: [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) (coluna Responsável)
- Dados: `responsavel_participante`, `presenca`, `feedback`, `manifestacao_privacidade`
- Critérios de aceite: [CA-04](../criterios-de-aceite.md#ca-04), [CA-11](../criterios-de-aceite.md#ca-11)
- API: `GET /me/participants`, `GET /me/participants/{id}/attendance`
