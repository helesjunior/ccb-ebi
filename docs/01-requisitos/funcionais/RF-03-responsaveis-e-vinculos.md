# RF-03 — Responsáveis e vínculos

**Status:** proposto · **Épico:** [E03](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. O responsável cria conta com e-mail verificado, senha e aceite dos termos pertinentes. No cadastro assistido, o responsável pode existir sem conta e reivindicar o vínculo depois ([FL-07](../fluxos/FL-07-cadastro-assistido.md)).
2. Registrar nome, e-mail, telefone (opcional) e relação com o participante. Pedir comprovação ou validação de legitimidade conforme o risco.
3. Permitir mais de um responsável por participante, com permissões e restrições individuais (por exemplo, guarda restrita).
4. Conhecer a matrícula não basta para vincular um participante a uma conta.
5. Fluxo de solicitação, aprovação, contestação e revogação de vínculo, com tratamento especial para guarda e decisões judiciais.
6. Não expor dados de um responsável a outro sem necessidade ou autorização.

## Relacionados
- Regras: [RN-05](../../02-regras-de-negocio/regras-de-negocio.md#rn-05), [RN-10](../../02-regras-de-negocio/regras-de-negocio.md#rn-10)
- Fluxos: [FL-01](../fluxos/FL-01-cadastro-responsavel-e-participante.md)
- Critérios de aceite: [CA-04](../criterios-de-aceite.md#ca-04)
- Dados: `responsavel_participante`, `solicitacao_vinculo`
- API: `POST /participants/{id}/guardians`, `/guardian-requests`

## Pendências
- [D-03](../../06-arquitetura/decisoes/README.md): como comprovar legitimidade, inclusive guarda compartilhada ou restrita.
- Concordância do próprio participante adulto (jurídico; ver [ADR-0012](../../06-arquitetura/decisoes/ADR-0012-excecao-decidida-pela-coordenadora.md)).
