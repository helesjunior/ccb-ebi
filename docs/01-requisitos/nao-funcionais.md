# Requisitos não funcionais

As metas numéricas abaixo são **propostas para negociação**, não compromissos aprovados ([D-11](../06-arquitetura/decisoes/README.md)).

## RNF-01 — Segurança
**Status:** proposto
- HTTPS/TLS moderno; criptografia em repouso; gestão segura de chaves e segredos.
- MFA obrigatório para todo perfil que não seja Responsável ([autenticação](../05-seguranca-e-privacidade/autenticacao.md)).
- Proteção contra brute force, credential stuffing, CSRF quando aplicável, XSS, SQL injection, IDOR/BOLA e uploads maliciosos.
- Política de acesso administrativo, segregação de ambientes, hardening, patching, análise de dependências, SAST/DAST e teste de invasão antes do lançamento.
- Logs sem senhas, tokens, códigos MFA ou dados excessivos de participantes.

## RNF-02 — Privacidade e LGPD
**Status:** proposto
Detalhado em [LGPD](../05-seguranca-e-privacidade/lgpd.md). Em resumo: inventário de dados, bases legais, RIPD, encarregado, minimização, retenção e descarte documentados, sem publicidade direcionada a crianças, e participação religiosa tratada como possível dado sensível.

## RNF-03 — Disponibilidade e continuidade
**Status:** proposto

| Meta | Piloto |
|---|---|
| Disponibilidade mensal | 99,5% |
| RPO | até 24 h |
| RTO | até 8 h |

- Backups automáticos criptografados, restauração testada e plano de contingência para registro manual de presença quando o sistema estiver indisponível.
- Monitoramento, alertas, métricas de erro e plano de resposta a incidentes.

## RNF-04 — Desempenho e escala
**Status:** proposto

| Operação | Meta (p95) |
|---|---|
| Busca de participante | até 2 s |
| Registro de presença | até 3 s |

- Metas valem sob carga-alvo definida em teste.
- Dimensionar com estimativas reais (tabela abaixo) e fazer testes de carga.
- PDFs, notificações e relatórios pesados processados de forma assíncrona.

**Volumetria a levantar** (sem esses números não há dimensionamento):

| Dado | Piloto Brasília | Nacional |
|---|---|---|
| Igrejas com EBI | a levantar | a levantar |
| Participantes cadastrados | a levantar | a levantar |
| Sessões simultâneas no horário de pico | a levantar | a levantar |
| Presenças por semana | a levantar | a levantar |
| PDFs na biblioteca e tamanho médio | a levantar | a levantar |

## RNF-05 — Usabilidade e acessibilidade
**Status:** proposto
- Interfaces responsivas, linguagem clara, fluxo de presença com poucos passos e mensagens de erro acionáveis.
- WCAG 2.2 AA como objetivo, com testes em dispositivos e leitores de tela.
- Fuso horário da igreja, formatos brasileiros (data, hora, telefone) e idioma pt-BR.
- Conexão móvel instável.
- Versões mínimas de Android, iOS e navegadores: a definir com a política da CCB.

## RNF-06 — Governança técnica
**Status:** proposto
- Infraestrutura sob gestão da CCB; homologação e produção segregadas.
- Apps publicados nas contas oficiais da CCB na Google Play e na App Store, condicionado a titularidade, certificados, políticas das lojas e aprovações internas ([D-07](../06-arquitetura/decisoes/README.md)).
- CI/CD, versionamento, documentação de arquitetura, OpenAPI, migrações de banco, observabilidade e política de atualização.

## RNF-07 — Retenção e registros
**Status:** proposto (novo na 2.0)
- Prazo de retenção definido por tipo de dado no [dicionário de dados](../03-dados/dicionario-de-dados.md), com aprovação do DPO ([D-04](../06-arquitetura/decisoes/README.md)).
- Retenção de logs técnicos e da trilha de auditoria definida separadamente; a trilha de auditoria não pode ser apagada antes do prazo.
- Anonimização, e não exclusão, de dados usados em estatísticas históricas, quando a base legal permitir.
