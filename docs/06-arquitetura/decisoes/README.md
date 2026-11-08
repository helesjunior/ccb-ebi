# Decisões

Duas listas: **decisões em aberto** (D-nn), que precisam de aprovação da CCB, do jurídico ou da segurança antes do desenvolvimento; e **ADRs**, decisões já tomadas ou propostas, com contexto e consequências. Quando uma D-nn é resolvida, ela ganha um ADR e o link aparece aqui.

## Decisões em aberto

| ID | Decisão | Quem decide | Bloqueia | Status | ADR |
|---|---|---|---|---|---|
| D-01 | Hierarquia formal e existência de vários EBIs por igreja | Administração CCB | RF-01, fase 1 | aberta | — |
| D-02 | Quem cria, aprova, mescla e corrige cadastros de participantes | Administração CCB | RF-02 | aberta | — |
| D-03 | Como comprovar legitimidade de responsáveis, inclusive guarda compartilhada ou restrita | CCB + jurídico | RF-03, FL-01 | aberta | — |
| D-04 | Bases legais, termos, retenção, descarte e procedimento de incidentes | Jurídico / DPO | RF-04, RNF-07, fase 1 | aberta | — |
| D-05 | Quais feedbacks os responsáveis veem e quem modera | CCB | RF-09 | aberta | — |
| D-06 | Política editorial, direitos autorais e publicação nacional dos PDFs | CCB + jurídico | RF-10 | aberta | — |
| D-07 | Um ou dois apps; quem tem a conta de desenvolvedor nas lojas | CCB + TI | RF-13, E11 | aberta | — |
| D-08 | Funcionamento offline e sincronização | Produto | E13 | aberta | — |
| D-09 | Equipe mínima por sessão e aprovação de escalas | CCB | RF-07, M-06 | aberta | — |
| D-10 | Exportação e acesso a dados identificáveis em níveis superiores | CCB + DPO | RF-14, matriz | aberta | — |
| D-11 | Infraestrutura, padrões tecnológicos e metas finais de SLA/RPO/RTO | TI CCB | RNF-03, arquitetura | aberta | — |
| D-12 | Confirmar a faixa de 3 a menos de 12 anos e aprovar o fluxo de autorização e revisão das exceções inclusivas, com responsáveis definidos | CCB | RN-13, RN-14, FL-06 | aberta | — |
| D-13 | Participante adulto com deficiência: consentimento próprio, apoiador ou curador, conta própria; tratamento de adolescentes de 12 a 17 anos | CCB + jurídico | RF-02, RF-03, LGPD | aberta (nova na 2.0) | — |
| D-14 | Regra para menores de 3 anos: bloquear, alertar ou permitir com autorização | CCB | RN-13 | aberta (nova na 2.0) | — |
| D-15 | Limiar de supressão em relatórios e definição de "participante ativo" e "EBI ativo" | Produto + DPO | RF-14, M-01, M-09 | aberta (nova na 2.0) | — |
| D-16 | Avaliação de atividade: uma nota por utilização ou por usuário e versão | Produto | RF-12 | aberta (nova na 2.0) | — |
| D-17 | Cadastro assistido pela coordenadora para famílias sem e-mail ou com pouca familiaridade digital | CCB + DPO | RF-02, FL-01 | aberta (nova na 2.0) | — |

## ADRs

| ID | Título | Status |
|---|---|---|
| [ADR-0001](ADR-0001-registro-de-decisoes.md) | Registrar decisões em ADRs | aceito |
| [ADR-0002](ADR-0002-monolito-modular.md) | Monólito modular no início | proposto |
| [ADR-0003](ADR-0003-entidade-participante.md) | Entidade `participante` no lugar de `crianca` | aceito |

Para criar um ADR, copie o [modelo](ADR-0001-registro-de-decisoes.md#modelo) e use o próximo número.
