# Pacote para o jurídico e o DPO

Lista curta do que o sistema precisa que o jurídico e o encarregado (DPO) da CCB validem antes do piloto com dados reais. Para cada item há um padrão provisório já adotado pelo produto ([ADR-0019](../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md)). Basta marcar **sim**, **não** ou **ajustar**, com a observação.

O sistema guarda dados de crianças e registra participação em atividade religiosa, que a LGPD trata como dado sensível (art. 11). A CCB é a controladora.

| # | Pergunta | Padrão provisório | Sim / Não / Ajustar |
|---|---|---|---|
| 1 | Qual a base legal para cadastro, presença e feedback? | Consentimento específico e destacado do responsável (art. 11, I, e art. 14, §1º). Notificações por opt-in. Logs de segurança e auditoria por exercício regular de direitos (art. 11, II, d) | |
| 2 | O termo de consentimento para crianças está adequado? | Linguagem simples, aceito por um dos pais ou responsável legal, com versão e data guardadas. Texto em rascunho a ser fornecido | |
| 3 | A participação religiosa deve ser tratada como dado sensível em toda a base? | Sim: acesso mínimo, criptografia e auditoria | |
| 4 | Como colher a concordância do participante adulto com deficiência (Lei 13.146/2015)? | Concordância do próprio participante quando puder se manifestar, junto com a do responsável ou apoiador, registrando quem concordou | |
| 5 | Qual evidência vale no cadastro feito pela coordenadora para famílias sem e-mail? | Termo em papel assinado, fotografado e anexado, com registro de quem colheu | |
| 6 | Os prazos de retenção estão adequados? | Cadastro e presenças: anonimizados 2 anos após a última presença. Feedback: 2 anos. Logs de acesso: 6 meses. Auditoria e evidência de consentimento: 5 anos. Laudo: apagado ao fim da exceção. Lista offline no aparelho: até 7 dias | |
| 7 | O procedimento de incidentes está adequado? | Resolução CD/ANPD nº 15/2024: DPO em até 24 h; ANPD e titulares em até 3 dias úteis quando houver risco relevante; registro de todo incidente | |
| 8 | Quem é o encarregado (DPO) e qual o canal do titular? | A CCB indica um encarregado; e-mail de privacidade exibido na tela de privacidade | |

## Também pedimos
- Política de privacidade ou de retenção que a CCB já tenha, para substituir o padrão provisório.
- Texto final do termo de consentimento e do aviso de privacidade.
- Validação das propostas de [D-06](../06-arquitetura/decisoes/ADR-0028-politica-editorial-dos-pdfs.md) (direitos dos PDFs) e [D-10](../06-arquitetura/decisoes/ADR-0029-dados-identificaveis-acima-da-igreja.md) (dados identificáveis acima da igreja).
