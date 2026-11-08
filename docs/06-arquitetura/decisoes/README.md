# Decisões

Duas listas: **decisões em aberto** (D-nn), que precisam de aprovação da CCB, do jurídico ou da segurança antes do desenvolvimento; e **ADRs**, decisões já tomadas ou propostas, com contexto e consequências. Quando uma D-nn é resolvida, ela ganha um ADR e o link aparece aqui.

## Decisões em aberto

| ID | Decisão | Quem decide | Bloqueia | Status | ADR |
|---|---|---|---|---|---|
| D-01 | Hierarquia formal e existência de vários EBIs por igreja | Administração CCB | RF-01, fase 1 | aberta | — |
| D-02 | Quem cria, aprova, mescla e corrige cadastros de participantes | Administração CCB | RF-02 | resolvida: responsável cria; coordenadora mescla e corrige | [ADR-0007](ADR-0007-cadastro-e-correcao-de-participantes.md) |
| D-03 | Como comprovar legitimidade de responsáveis, inclusive guarda compartilhada ou restrita | CCB + jurídico | RF-03, FL-01 | aberta | — |
| D-04 | Bases legais, termos, retenção, descarte e procedimento de incidentes | Jurídico / DPO | RF-04, RNF-07, fase 1 | aberta | — |
| D-05 | Quais feedbacks os responsáveis veem e quem modera | CCB | RF-09 | resolvida: só após moderação do Ministério Local | [ADR-0008](ADR-0008-moderacao-de-feedback.md) |
| D-06 | Política editorial, direitos autorais e publicação nacional dos PDFs | CCB + jurídico | RF-10 | aberta | — |
| D-07 | Um ou dois apps; quem tem a conta de desenvolvedor nas lojas | CCB + TI | RF-13, E11 | em parte: um app único; titular das lojas em aberto | [ADR-0009](ADR-0009-aplicativo-unico.md) |
| D-08 | Funcionamento offline e sincronização | Produto | E13 | aberta; opções e recomendação abaixo | — |
| D-09 | Equipe mínima por sessão e aprovação de escalas | CCB | RF-07, M-06 | em parte: aprovação pela Coordenadora chefe; tamanho da equipe em aberto | [ADR-0010](ADR-0010-equipe-minima-e-aprovacao-de-escala.md) |
| D-10 | Exportação e acesso a dados identificáveis em níveis superiores | CCB + DPO | RF-14, matriz | aberta | — |
| D-11 | Infraestrutura, padrões tecnológicos e metas finais de SLA/RPO/RTO | TI CCB | RNF-03, arquitetura | aberta; proposta para o piloto nos ADRs 0004 a 0006 | [ADR-0004](ADR-0004-conteineres-e-orquestracao.md) |
| D-12 | Confirmar a faixa de 3 a menos de 12 anos e aprovar o fluxo de autorização e revisão das exceções inclusivas | CCB | RN-13, RN-14, FL-06 | resolvida | [ADR-0011](ADR-0011-faixa-etaria-confirmada.md), [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| D-13 | Participante adulto com deficiência; adolescentes de 12 a 17 anos | CCB + jurídico | RF-02, RF-03, LGPD | resolvida pela CCB: sem limite de idade, coordenadora do dia decide com o responsável; validação jurídica pendente em D-04 | [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| D-14 | Regra para menores de 3 anos | CCB | RN-13 | resolvida: bloquear | [ADR-0013](ADR-0013-bloqueio-menores-de-3-anos.md) |
| D-15 | Limiar de supressão em relatórios e definição de "participante ativo" e "EBI ativo" | Produto + DPO | RF-14, M-01, M-09 | aberta (nova na 2.0) | — |
| D-16 | Avaliação de atividade: uma nota por utilização ou por usuário e versão | Produto | RF-12 | resolvida: por utilização | [ADR-0014](ADR-0014-avaliacao-por-utilizacao.md) |
| D-17 | Cadastro assistido pela coordenadora para famílias sem e-mail ou com pouca familiaridade digital | CCB + DPO | RF-02, FL-01 | resolvida: permitido; evidência de consentimento com o DPO | [ADR-0015](ADR-0015-cadastro-assistido.md) |

## ADRs

| ID | Título | Status |
|---|---|---|
| [ADR-0001](ADR-0001-registro-de-decisoes.md) | Registrar decisões em ADRs | aceito |
| [ADR-0002](ADR-0002-monolito-modular.md) | Monólito modular no início | proposto |
| [ADR-0003](ADR-0003-entidade-participante.md) | Entidade `participante` no lugar de `crianca` | aceito |
| [ADR-0004](ADR-0004-conteineres-e-orquestracao.md) | Contêineres Docker e orquestração com Compose no piloto | proposto |
| [ADR-0005](ADR-0005-armazenamento-e-antivirus.md) | Armazenamento de PDFs e verificação antivírus | proposto |
| [ADR-0006](ADR-0006-segredos-backups-e-observabilidade.md) | Segredos, backups e observabilidade | proposto |
| [ADR-0007](ADR-0007-cadastro-e-correcao-de-participantes.md) | Quem cria, mescla e corrige cadastros de participantes | aceito |
| [ADR-0008](ADR-0008-moderacao-de-feedback.md) | Moderação de feedback pelo Ministério Local | aceito |
| [ADR-0009](ADR-0009-aplicativo-unico.md) | Um único aplicativo para todos os perfis | aceito |
| [ADR-0010](ADR-0010-equipe-minima-e-aprovacao-de-escala.md) | Equipe mínima e aprovação de escalas | aceito |
| [ADR-0011](ADR-0011-faixa-etaria-confirmada.md) | Faixa etária regular confirmada | aceito |
| [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) | Exceção inclusiva decidida pela coordenadora com o responsável | aceito |
| [ADR-0013](ADR-0013-bloqueio-menores-de-3-anos.md) | Menores de 3 anos não participam | aceito |
| [ADR-0014](ADR-0014-avaliacao-por-utilizacao.md) | Avaliação de atividade por utilização | aceito |
| [ADR-0015](ADR-0015-cadastro-assistido.md) | Cadastro assistido pela coordenadora | aceito |

## D-08 — Opções para funcionamento offline

| Opção | O que é | Custo | Risco |
|---|---|---|---|
| A. Só online, com contingência em papel | Sem internet, a coordenadora anota as presenças numa lista e lança depois no sistema | Nenhum desenvolvimento extra | Retrabalho e erro de digitação quando falta internet |
| B. Offline só para presença | O app guarda na fila as presenças da sessão aberta e envia quando a conexão volta; o servidor resolve duplicidades | Médio: cache dos participantes do EBI no aparelho, fila e sincronização | Dados de crianças armazenados no celular (precisa de criptografia e limpeza) |
| C. Offline completo | Todas as funções funcionam sem internet | Alto | Conflitos de edição difíceis de resolver; mais dados no aparelho |

**Recomendação:** começar com **A** no piloto e medir quantas sessões ficam sem internet. Se o problema for frequente, implementar **B** junto com o app (fase 3). A opção C não compensa para o EBI.

Para criar um ADR, copie o [modelo](ADR-0001-registro-de-decisoes.md#modelo) e use o próximo número.
