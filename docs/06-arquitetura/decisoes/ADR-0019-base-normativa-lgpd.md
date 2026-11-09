# ADR-0019 — Base normativa de privacidade

**Status:** aceito em parte · **Data:** 08/11/2026, padrão provisório em 09/11/2026 · **Aprovado por:** Heles Júnior (produto); padrão provisório aguarda jurídico e DPO · **Resolve em parte:** D-04

## Decisão
Bases legais, termos, retenção, descarte e procedimento de incidentes seguem **as normas aplicáveis à CCB como instituição filantrópica sem fins lucrativos**. Ser filantrópica não dispensa a LGPD; a CCB continua sendo controladora dos dados.

## Padrão provisório
Para não parar o desenvolvimento, o produto adotou o padrão conservador abaixo. Ele vale para desenvolvimento e homologação com **dados fictícios**. **Nenhum dado real entra no sistema antes do parecer do jurídico e do DPO** sobre estes itens (portão da etapa 8 no [plano](../../07-produto/plano-de-desenvolvimento.md)). A lista para o jurídico está em [pacote para o jurídico](../../05-seguranca-e-privacidade/pacote-para-o-juridico.md).

| # | Item | Padrão provisório | Por que o sistema precisa |
|---|---|---|---|
| 1 | Base legal por finalidade | Cadastro, presença e feedback: **consentimento específico e destacado** do responsável (art. 11, I, e art. 14, §1º). Notificações: opt-in. Logs de segurança e auditoria: exercício regular de direitos (art. 11, II, d). Relatórios acima da igreja: só agregados ([ADR-0029](ADR-0029-dados-identificaveis-acima-da-igreja.md)) | Define o que exige consentimento e o que pode ser retirado (RF-04) |
| 2 | Dados de crianças (art. 14) | Termo em linguagem simples, aceito por um dos pais ou responsável legal, com versão e data guardadas | Texto do termo e de quem é o consentimento |
| 3 | Participação religiosa como dado sensível (art. 11) | Toda a base de presenças é tratada como sensível: acesso mínimo, criptografia e auditoria | Controles reforçados |
| 4 | Participante adulto com deficiência | Registrar a concordância do próprio participante quando ele puder se manifestar, junto com a do responsável ou apoiador, e anotar quem concordou | Complemento do [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| 5 | Evidência no cadastro assistido | Termo em papel assinado, fotografado e anexado, com registro de quem colheu | [ADR-0015](ADR-0015-cadastro-assistido.md) |
| 6 | Retenção e descarte | Prazos na tabela abaixo, todos configuráveis | [Dicionário de dados](../../03-dados/dicionario-de-dados.md), RNF-07 |
| 7 | Incidentes | Segue a Resolução CD/ANPD nº 15/2024: DPO avisado em até 24 h; ANPD e titulares em até 3 dias úteis quando houver risco ou dano relevante; todo incidente registrado | Plano de resposta (RNF-03) |
| 8 | Encarregado (DPO) e canal do titular | A CCB indica um encarregado provisório; o sistema sai com e-mail de privacidade configurável na tela de privacidade | RF-16 e tela de privacidade |

### Prazos de retenção provisórios

| Dado | Prazo |
|---|---|
| Cadastro de participante sem presença, com presenças e vínculos | Anonimizado 2 anos após a última presença (ou após o cadastro, se nunca houve presença) |
| Feedback | 2 anos após a liberação |
| Logs de acesso | 6 meses |
| Auditoria e evidência de consentimento | 5 anos após o fim do tratamento |
| Laudo ou diagnóstico | Apagado quando a exceção termina |
| Lista offline no aparelho ([ADR-0016](ADR-0016-offline-para-presenca.md)) | Até a sincronização, no máximo 7 dias |

Estatísticas históricas usam dados anonimizados e não têm prazo.

## Consequências
- D-04 continua **em parte** até o parecer do jurídico e do DPO; o padrão provisório deixa o desenvolvimento seguir.
- Todo prazo é configuração, não código. Textos de termo ficam marcados como **rascunho** até a aprovação.
- Se a CCB já tiver política de privacidade ou de retenção, ela deve ser anexada a `docs/05-seguranca-e-privacidade/` e prevalece sobre este padrão.
