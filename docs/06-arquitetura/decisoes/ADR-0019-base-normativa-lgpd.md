# ADR-0019 — Base normativa de privacidade

**Status:** aceito em parte · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve em parte:** D-04

## Decisão
Bases legais, termos, retenção, descarte e procedimento de incidentes seguem **as normas aplicáveis à CCB como instituição filantrópica sem fins lucrativos**.

## O que ainda depende do jurídico/DPO
Ser filantrópica não dispensa a LGPD; a CCB continua sendo controladora dos dados. A decisão acima define a referência, mas o sistema precisa destes itens por escrito antes da fase 1:

| Item | Por que o sistema precisa |
|---|---|
| Base legal por finalidade (cadastro, presença, feedback, notificações, relatórios) | Define o que exige consentimento e o que pode ser retirado (RF-04) |
| Tratamento de dados de crianças (LGPD, art. 14) | Texto do termo e de quem é o consentimento |
| Participação religiosa como dado sensível (art. 11) | Controles reforçados sobre toda a base de presenças |
| Concordância do participante adulto com deficiência | Complemento do [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| Evidência de consentimento no cadastro assistido | [ADR-0015](ADR-0015-cadastro-assistido.md) |
| Prazos de retenção e descarte por tipo de dado | [Dicionário de dados](../../03-dados/dicionario-de-dados.md), RNF-07 |
| Procedimento de incidentes e comunicação à ANPD e aos titulares | Plano de resposta (RNF-03) |
| Indicação do encarregado (DPO) e canal do titular | RF-16 e tela de privacidade |

## Consequências
- D-04 fica como **em parte**: a referência normativa está definida; os itens acima seguem em aberto.
- Se a CCB já tiver política de privacidade ou de retenção, ela deve ser anexada a `docs/05-seguranca-e-privacidade/` e usada como fonte.
