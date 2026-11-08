# Riscos

| ID | Risco | Impacto | Tratamento | Referência |
|---|---|---|---|---|
| R-01 | Dados de crianças e participação religiosa | Alto risco de privacidade e exposição sensível | Minimização, avaliação jurídica/RIPD, controles reforçados, política de retenção | [LGPD](../05-seguranca-e-privacidade/lgpd.md) |
| R-02 | Responsável fraudulento reivindica participante | Exposição do histórico | Validação de vínculo e contestação; matrícula nunca é segredo de acesso | RF-03, D-03 |
| R-03 | Busca nacional aberta por nome | Consulta indevida e enumeração | Busca contextual, limites, justificativa quando necessário, auditoria | RF-02 |
| R-04 | Geolocalização imprecisa ou falsificada | Presença na igreja errada | GPS só sugere; confirmação de EBI/sessão pelo operador | RN-04 |
| R-05 | PDF com direitos de terceiros | Risco jurídico e reputacional | Declaração de direitos, moderação, retirada rápida | RF-10, D-06 |
| R-06 | Feedback subjetivo ou sensível | Estigmatização do participante | Campos objetivos, treinamento, moderação, direito de correção | RF-09, D-05 |
| R-07 | Estatísticas com dupla contagem | Decisões equivocadas | Métricas distintas por participante único, sessão e presença | [métricas](../02-regras-de-negocio/metricas-e-indicadores.md) |
| R-08 | Falta de internet na igreja | Perda de registros | Procedimento de contingência; modo offline seguro se necessário | RNF-03, D-08 |
| R-09 | Mudança na hierarquia administrativa | Perda de contexto histórico | Vínculos com vigência e histórico organizacional | RN-09 |
| R-10 | WhatsApp como canal de MFA | Custo, dependência, tomada de conta | Preferir TOTP/passkey; WhatsApp só após avaliação | [autenticação](../05-seguranca-e-privacidade/autenticacao.md) |
| R-11 | Publicação em lojas oficiais | Dependência de governança e políticas | Confirmar titularidade, responsáveis e conformidade | D-07 |
| R-12 | Exceção inclusiva mal definida para adultos *(novo)* | Consentimento inválido; exposição de dado sensível | Resolver D-13 antes da fase 1 | [elegibilidade](../02-regras-de-negocio/elegibilidade-etaria.md) |
| R-13 | Exclusão de famílias sem e-mail *(novo)* | Baixa adesão no piloto | Cadastro assistido | D-17 |
