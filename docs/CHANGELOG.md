# Histórico de versões

Formato: versão, data, autor e mudanças. Itens mais recentes primeiro.

## 2.3 — 08/11/2026

### Decisões (ADR-0017 a ADR-0019)
- D-01: hierarquia Central (Brás) → Regional → Setor → Igreja → EBI, com Ministério Regional, Setorial e Local; mais de um EBI por igreja é permitido (ADR-0017). Novos perfis Ministério Regional e Ministério Setorial.
- D-03: certidão de nascimento ou documento judicial, pedido pela coordenadora só em caso de dúvida (ADR-0018). Novo estado de vínculo `aguardando_documento`.
- D-04: referência nas normas para instituição filantrópica; itens para o jurídico listados (ADR-0019).
- D-09 concluída: 1 coordenadora + 1 auxiliar para cada 4 participantes (estimativa), com aviso durante a sessão (ADR-0010 atualizado).
- Novo perfil Ministério da Central (visão nacional); administradores podem acumular o papel de ministério (ADR-0017).
- D-15: "< 5" para contagens de 1 a 4; participante ativo = presença em 12 meses ou cadastro em 90 dias; EBI ativo = sessão em 90 dias (ADR-0020).

### Documentos atualizados
- Visão geral (hierarquia), perfis, glossário, RF-01, RF-03, FL-01, estados, LGPD, métricas.

## 2.2 — 08/11/2026

Registro das decisões tomadas por Heles Júnior em 08/11/2026.

### Decisões (ADR-0007 a ADR-0015)
- D-02: o responsável cria o cadastro; a coordenadora mescla e corrige (ADR-0007).
- D-05: feedback só chega ao responsável depois de moderado pelo irmão do Ministério Local (ADR-0008). Novo perfil **Ministério Local**.
- D-07: um único app para todas as visões (ADR-0009). Titular das lojas continua em aberto.
- D-09: equipe mínima obrigatória e escala aprovada pela **coordenadora chefe** (ADR-0010). Tamanho da equipe continua em aberto.
- D-12: faixa de 3 anos até a véspera do 12º aniversário confirmada (ADR-0011).
- D-13: exceção sem limite de idade, decidida pela coordenadora do dia com o responsável (ADR-0012). Validação jurídica pendente.
- D-14: menores de 3 anos bloqueados (ADR-0013).
- D-16: avaliação por utilização (ADR-0014).
- D-17: cadastro assistido pela coordenadora (ADR-0015).
- D-08: offline só para registro de presença, junto com o app na fase 3 (ADR-0016).

### Documentos atualizados
- Perfis, glossário, matriz de autorização (coluna Ministério Local), visão geral (app único).
- Elegibilidade etária, FL-01, FL-04, FL-05, FL-06; novo FL-07 (cadastro assistido).
- RF-02, RF-03, RF-07, RF-09, RF-12, RF-13; novos critérios CA-19 a CA-22.
- Modelo de dados, estados (novo diagrama de escala), endpoints, backlog, riscos (R-14), LGPD.

## 2.1 — 08/11/2026

### Arquitetura
- ADR-0004: contêineres Docker e orquestração com Compose, uma VM por ambiente no piloto.
- ADR-0005: PDFs em MinIO com bucket de quarentena e verificação por ClamAV.
- ADR-0006: segredos em arquivos montados, backups com WAL-G fora da VM e stack de observabilidade.
- Arquitetura de referência atualizada: Laravel confirmado e diagrama de contêineres.
- Pasta `infra/` com rascunho do `compose.yml`.

## 2.0 — 08/11/2026

Reorganização da especificação em documentos por assunto, com correções de consistência. O conteúdo original está preservado em [arquivo/especificacao-v2-original.md](arquivo/especificacao-v2-original.md).

### Estrutura
- Documento único dividido na pasta `docs/`, com IDs estáveis, status por item e matriz de rastreabilidade.
- Regra de idade e exceção inclusiva consolidada em um só documento.
- Decisões D-01 a D-12 movidas para o índice de decisões; ADR-0001 a ADR-0003 criados.

### Correções
- Versão: o arquivo se chamava "v2" e o cabeçalho dizia "1.0". Adotada a versão 2.0 com este histórico.
- Critérios de aceite: a numeração repetia 6 e 7. Renumerados de CA-01 a CA-16 e ligados aos requisitos.
- Regras de negócio: RN-13 a RN-15 apareciam antes de RN-02. Mantidos os IDs, exibição agrupada por domínio.
- Entidade `crianca`, descrita como "legada" sem existir sistema legado, renomeada para `participante` (ADR-0003).
- RN-15 citava "vencimento" da exceção sem status nem campo correspondente. Incluídos o status `expirada` e o campo `vigente_ate`.
- RN-09 exigia preservar a igreja histórica da sessão, mas o modelo só guardava `ebi_id`. A sessão agora grava a unidade territorial vigente na data (snapshot).
- Modelo de dados: incluída a entidade `central_nacional`; `presenca` ganhou origem e campos de estorno; `utilizacao_atividade` ganhou adaptação local.
- Fases × backlog: aplicativos (E11) e geolocalização (E12) estavam em prioridades que não batiam com as fases. Cada épico agora aponta para uma fase.
- Validação de idade/exceção incluída como item P0 do épico E04, pois é pré-requisito do registro de presença no MVP.
- MFA: explicitado que é obrigatório para todo perfil que não seja Responsável, incluindo perfis de Consulta.
- API: rota `/ebis/{id}/roster` substituída por `/ebis/{id}/schedules` para evitar ambiguidade com lista de participantes; `/children` virou `/participants`.
- Perfil "Consulta Todas Regionais" renomeado para "Consulta Nacional".

### Acréscimos
- FL-06: fluxo de solicitação e revisão da exceção inclusiva (não existia).
- RN-16: cálculo da idade no fuso da igreja e regra para nascidos em 29/02.
- CA-17 (exceção revogada ou expirada) e CA-18 (transferência de igreja preserva histórico).
- RNF-07: retenção e registros.
- Diagramas de estado para exceção, sessão, presença, vínculo, feedback e atividade.
- Matriz de autorização inicial (perfil × recurso).
- Endpoints que faltavam: ciclo da sessão, estorno, exceção, escalas, moderação, estrutura organizacional, recuperação de senha.
- Entidades que faltavam: histórico de vinculação da igreja, exceções de agenda, troca de escala, denúncia, solicitações de vínculo e do titular, fatores MFA, convites.
- Riscos R-12 e R-13.
- Proposta de aviso ao responsável antes do 12º aniversário.

### Lacunas registradas como decisões em aberto
- D-13: participante adulto com deficiência (capacidade civil, consentimento, conta própria).
- D-14: regra para menores de 3 anos.
- D-15: limiar de supressão nos relatórios e definição de "ativo".
- D-16: regra de avaliação de atividades (uma nota por utilização ou por usuário e versão).
- D-17: cadastro assistido para famílias sem e-mail.

## 1.0 — data não registrada

Versão inicial em documento único (arquivo recebido como "v2", com cabeçalho "Versão 1.0").
