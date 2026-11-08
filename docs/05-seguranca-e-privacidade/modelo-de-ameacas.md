# Modelo de ameaças

**Status:** esqueleto, a preencher na fase 0 ([entregável 5](../07-produto/entregaveis.md)).

Método sugerido: STRIDE por componente da [arquitetura de referência](../06-arquitetura/arquitetura-de-referencia.md).

## Ameaças já identificadas na especificação

| Ameaça | Componente | Controle previsto | Referência |
|---|---|---|---|
| Responsável fraudulento reivindica participante | Vínculos | Validação de legitimidade; matrícula não é segredo | RF-03, [R-02](../07-produto/riscos.md) |
| Enumeração por busca nacional de nomes | Pesquisa | Busca contextual, limites, auditoria | RF-02, R-03 |
| IDOR/BOLA entre igrejas | API | ABAC no servidor; testes horizontais e verticais | [matriz](matriz-de-autorizacao.md), CA-03 |
| GPS falsificado | App equipe | GPS só sugere; confirmação manual | RN-04, R-04 |
| PDF malicioso | Biblioteca | Antivírus, validação de tipo real, storage privado | RF-10 |
| Tomada de conta por MFA fraco | Identidade | TOTP/passkey; WhatsApp só após análise | [autenticação](autenticacao.md), R-10 |
| Vazamento por logs | Observabilidade | Logs sem segredos e com dados minimizados | RNF-01 |
| Exportação indevida | Relatórios | Autorização de exportação, supressão, auditoria | RF-14, D-10 |

## A fazer
- [ ] Diagrama de fluxo de dados com fronteiras de confiança.
- [ ] STRIDE por componente.
- [ ] Priorização e ligação com o backlog.
