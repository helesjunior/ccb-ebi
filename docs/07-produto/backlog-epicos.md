# Backlog — épicos

Cada épico aponta para uma fase. A prioridade segue a fase: **P0** = fase 1 (MVP), **P1** = fase 2, **P2** = fase 3 ou posterior. É recomendação preliminar.

| Épico | Itens principais | Requisitos | Fase | Prioridade |
|---|---|---|---|---|
| E01 Identidade | Login, verificação de e-mail, MFA, recuperação, papéis/escopos, equipe | RF-05, autenticação | 1 | P0 |
| E02 Organização | Central, regional, setor, igreja, EBI, transferências, geocadastro | RF-01 | 1 | P0 |
| E03 Participantes e responsáveis | Cadastro único, vínculo, deduplicação, cadastro de exceção inclusiva | RF-02, RF-03 | 1 | P0 |
| E04 Sessões e frequência | Agenda, abertura, pesquisa, **validação de elegibilidade etária**, registro, estorno, histórico | RF-06, RF-08 | 1 | P0 |
| E05 Privacidade e auditoria | Termos, consentimentos, direitos do titular, trilhas, retenção, controles de acesso | RF-04, RF-16 | 1 | P0 |
| E06 Relatórios básicos | Participantes distintos, presenças, sessões, filtros, supressão | RF-14 | 1 | P0 |
| E07 Escalas | Planejamento, confirmação, trocas, conflitos | RF-07 | 2 | P1 |
| E08 Biblioteca | PDFs, metadados bíblicos, moderação, pesquisa | RF-10 | 2 | P1 |
| E09 Reutilização e avaliação | Uso por sessão, histórico, estrelas, indicadores | RF-11, RF-12 | 2 | P1 |
| E10 Feedback | Registro, moderação, visualização pelo responsável | RF-09 | 2 | P1 |
| E11 Apps | Apps de equipe e de responsáveis, publicação nas lojas | RF-13 | 3 | P2 |
| E12 Geolocalização e notificações | Sugestão de EBI, push, WhatsApp opcional (e-mail transacional já entra no E01) | RF-08, RF-15 | 3 | P2 |
| E13 Continuidade | Offline, sincronização, resiliência avançada | RF-08, RNF-03 | 3 ou 4, conforme o piloto | P2 |

## Mudanças em relação à versão anterior
- E11 Apps era P1 mas estava na fase 3; agora é P2 e fase 3, coerente com o roadmap.
- E04 passou a incluir explicitamente a validação de elegibilidade etária, pré-requisito do registro de presença no MVP.
- E03 inclui o cadastro da exceção inclusiva.
