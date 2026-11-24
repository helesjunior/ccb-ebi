# Decisões

Duas listas: **decisões em aberto** (D-nn), que precisam de aprovação da CCB, do jurídico ou da segurança antes do desenvolvimento; e **ADRs**, decisões já tomadas ou propostas, com contexto e consequências. Quando uma D-nn é resolvida, ela ganha um ADR e o link aparece aqui.

## Decisões em aberto

| ID | Decisão | Quem decide | Bloqueia | Status | ADR |
|---|---|---|---|---|---|
| D-01 | Hierarquia formal e existência de vários EBIs por igreja | Administração CCB | RF-01, fase 1 | resolvida: Central → Regional → Setor → Igreja → EBI, com ministério em cada nível; vários EBIs por igreja permitidos | [ADR-0017](ADR-0017-hierarquia-e-ministerios.md) |
| D-02 | Quem cria, aprova, mescla e corrige cadastros de participantes | Administração CCB | RF-02 | resolvida: responsável cria; coordenadora mescla e corrige | [ADR-0007](ADR-0007-cadastro-e-correcao-de-participantes.md) |
| D-03 | Como comprovar legitimidade de responsáveis, inclusive guarda compartilhada ou restrita | CCB + jurídico | RF-03, FL-01 | resolvida: certidão ou documento judicial, pedido pela coordenadora só em caso de dúvida | [ADR-0018](ADR-0018-comprovacao-de-responsavel.md) |
| D-04 | Bases legais, termos, retenção, descarte e procedimento de incidentes | Jurídico / DPO | RF-04, RNF-07, fase 1 | em parte: padrão provisório adotado pelo produto; aguarda parecer do jurídico e do DPO antes de dados reais | [ADR-0019](ADR-0019-base-normativa-lgpd.md) |
| D-05 | Quais feedbacks os responsáveis veem e quem modera | CCB | RF-09 | resolvida: só após moderação do Ministério Local | [ADR-0008](ADR-0008-moderacao-de-feedback.md) |
| D-06 | Política editorial, direitos autorais e publicação nacional dos PDFs | CCB + jurídico | RF-10 | em parte: regra conservadora aprovada pelo produto; aguarda CCB e jurídico | [ADR-0028](ADR-0028-politica-editorial-dos-pdfs.md) |
| D-07 | Um ou dois apps; quem tem a conta de desenvolvedor nas lojas | CCB + TI | RF-13, E11 | em parte: um app único; titular das lojas em aberto | [ADR-0009](ADR-0009-aplicativo-unico.md) |
| D-08 | Funcionamento offline e sincronização | Produto | E13 | resolvida: offline só para presença, com o app | [ADR-0016](ADR-0016-offline-para-presenca.md) |
| D-09 | Equipe mínima por sessão e aprovação de escalas | CCB | RF-07, M-06 | resolvida: 1 coordenadora + 1 auxiliar a cada 4 participantes; aprovação pela coordenadora chefe | [ADR-0010](ADR-0010-equipe-minima-e-aprovacao-de-escala.md) |
| D-10 | Exportação e acesso a dados identificáveis em níveis superiores | CCB + DPO | RF-14, matriz | em parte: só agregados acima da igreja, sem exportação com nomes no MVP; aguarda CCB e DPO | [ADR-0029](ADR-0029-dados-identificaveis-acima-da-igreja.md) |
| D-11 | Infraestrutura, padrões tecnológicos e metas finais de SLA/RPO/RTO | TI CCB | RNF-03, arquitetura | aberta; proposta para o piloto nos ADRs 0004 a 0006 | [ADR-0004](ADR-0004-conteineres-e-orquestracao.md) |
| D-12 | Confirmar a faixa de 3 a menos de 12 anos e aprovar o fluxo de autorização e revisão das exceções inclusivas | CCB | RN-13, RN-14, FL-06 | resolvida | [ADR-0011](ADR-0011-faixa-etaria-confirmada.md), [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| D-13 | Participante adulto com deficiência; adolescentes de 12 a 17 anos | CCB + jurídico | RF-02, RF-03, LGPD | em parte: regra de produto aprovada (sem limite de idade, coordenadora do dia decide com o responsável); concordância do participante adulto pendente no jurídico ([ADR-0019](ADR-0019-base-normativa-lgpd.md)) | [ADR-0012](ADR-0012-excecao-decidida-pela-coordenadora.md) |
| D-14 | Regra para menores de 3 anos | CCB | RN-13 | resolvida: bloquear | [ADR-0013](ADR-0013-bloqueio-menores-de-3-anos.md) |
| D-15 | Limiar de supressão em relatórios e definição de "participante ativo" e "EBI ativo" | Produto + DPO | RF-14, M-01, M-09 | resolvida: "< 5" para contagens de 1 a 4; ativo = presença em 12 meses ou cadastro em 90 dias; EBI ativo = sessão em 90 dias | [ADR-0020](ADR-0020-supressao-e-definicao-de-ativo.md) |
| D-16 | Avaliação de atividade: uma nota por utilização ou por usuário e versão | Produto | RF-12 | resolvida: por utilização | [ADR-0014](ADR-0014-avaliacao-por-utilizacao.md) |
| D-17 | Cadastro assistido pela coordenadora para famílias sem e-mail ou com pouca familiaridade digital | CCB + DPO | RF-02, FL-01 | resolvida: permitido; evidência de consentimento com o DPO | [ADR-0015](ADR-0015-cadastro-assistido.md) |
| D-18 | Linguagem de gênero nos perfis (Coordenadora/Auxiliar) | Produto | Telas | resolvida: papel sem gênero; tela segue o cadastro da pessoa | [ADR-0023](ADR-0023-linguagem-de-genero.md) |
| D-19 | Escala de participação no feedback | Produto | RF-09 | resolvida: 3 níveis | [ADR-0024](ADR-0024-escala-de-participacao.md) |
| D-20 | Mínimo de avaliações para mostrar a média de uma atividade | Produto | RF-12 | resolvida: 5 avaliações | [ADR-0025](ADR-0025-minimo-de-avaliacoes.md) |
| D-21 | Confirmações pendentes do bloco de idade: RN-15, RN-16, cadastro antecipado de menores de 3 anos, validade da exceção entre sessões | Produto | E04 | resolvida: as quatro propostas aprovadas | [ADR-0026](ADR-0026-confirmacoes-do-bloco-de-idade.md) |
| D-22 | Escopo da coordenadora para mesclar e corrigir cadastros | Produto | RF-02 | resolvida: só participantes ligados aos EBIs da coordenadora | [ADR-0027](ADR-0027-escopo-de-mesclagem.md) |

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
| [ADR-0016](ADR-0016-offline-para-presenca.md) | Offline só para registro de presença | aceito |
| [ADR-0017](ADR-0017-hierarquia-e-ministerios.md) | Hierarquia da CCB e ministérios por nível | aceito |
| [ADR-0018](ADR-0018-comprovacao-de-responsavel.md) | Comprovação de responsável só em caso de dúvida | aceito |
| [ADR-0019](ADR-0019-base-normativa-lgpd.md) | Base normativa de privacidade | aceito em parte |
| [ADR-0020](ADR-0020-supressao-e-definicao-de-ativo.md) | Supressão em relatórios e definição de "ativo" | aceito |
| [ADR-0021](ADR-0021-portal-web-react.md) | Portal Web em React com TypeScript | aceito |
| [ADR-0022](ADR-0022-identidade-e-acesso.md) | Identidade e acesso (Etapa 3): escopo e dívidas | aceito |
| [ADR-0023](ADR-0023-linguagem-de-genero.md) | Linguagem de gênero nos perfis | aceito |
| [ADR-0024](ADR-0024-escala-de-participacao.md) | Escala de participação no feedback | aceito |
| [ADR-0025](ADR-0025-minimo-de-avaliacoes.md) | Mínimo de avaliações para exibir a média | aceito |
| [ADR-0026](ADR-0026-confirmacoes-do-bloco-de-idade.md) | Confirmações do bloco de idade | aceito |
| [ADR-0027](ADR-0027-escopo-de-mesclagem.md) | Escopo da coordenadora para mesclar e corrigir cadastros | aceito |
| [ADR-0028](ADR-0028-politica-editorial-dos-pdfs.md) | Política editorial e direitos dos PDFs | proposto pelo produto; aguarda CCB e jurídico |
| [ADR-0029](ADR-0029-dados-identificaveis-acima-da-igreja.md) | Dados identificáveis acima da igreja | proposto pelo produto; aguarda CCB e DPO |
| [ADR-0030](ADR-0030-privacidade-e-auditoria.md) | Privacidade e auditoria (Etapa 4, A1): escopo e dívidas | aceito |
| [ADR-0031](ADR-0031-dados-sensiveis-e-retencao.md) | Dados sensíveis e retenção (Etapa 4, A2): escopo e dívidas | aceito |
| [ADR-0032](ADR-0032-vinculos-responsavel-e-participante.md) | Vínculos responsável↔participante e cadastro do participante (Etapa 5, E03) | aceito |
| [ADR-0033](ADR-0033-coordenadora-chefe-e-regras-da-escala.md) | Coordenadora chefe e regras da escala (Etapa 7, E07) | aceito |

Para criar um ADR, copie o [modelo](ADR-0001-registro-de-decisoes.md#modelo) e use o próximo número.
