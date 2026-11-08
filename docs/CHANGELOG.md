# Histórico de versões

Formato: versão, data, autor e mudanças. Itens mais recentes primeiro.

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
