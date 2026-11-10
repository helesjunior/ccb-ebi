# Matriz de rastreabilidade

Liga cada requisito funcional às regras, fluxos, critérios de aceite e épico. As colunas "Histórias" e "Testes" serão preenchidas quando o backlog for detalhado (exigência do [entregável 1](../07-produto/entregaveis.md)).

| RF | Regras | Fluxos | Critérios de aceite | Épico | Histórias | Testes |
|---|---|---|---|---|---|---|
| RF-01 | RN-09 | — | CA-18 | E02 | | |
| RF-02 | RN-01, RN-12, RN-13, RN-14, RN-15, RN-16 | FL-01, FL-06, FL-07 | CA-01, CA-05, CA-06, CA-17, CA-19, CA-21, CA-22, CA-25 | E03 | | |
| RF-03 | RN-05, RN-10 | FL-01, FL-07 | CA-04, CA-21 | E03 | | |
| RF-04 | RN-10, RN-12 | FL-01 | CA-16 | E05 | | |
| RF-05 | RN-11 | — | CA-03 | E01 | | |
| RF-06 | RN-02, RN-09 | — | CA-23 | E04 | | |
| RF-07 | — | FL-04 | CA-12, CA-20 | E07 | | |
| RF-08 | RN-02, RN-03, RN-04, RN-10, RN-13 a RN-16 | FL-02 | CA-02, CA-05, CA-07, CA-13, CA-17, CA-19 | E04, E12 | | |
| RF-09 | RN-10 | FL-05 | CA-11 | E10 | | |
| RF-10 | RN-12 | FL-03 | CA-08 | E08 | | |
| RF-11 | RN-06, RN-07 | FL-03 | CA-09 | E09 | | |
| RF-12 | RN-08 | FL-03 | CA-10 | E09 | | |
| RF-13 | RN-05 | FL-02 | CA-04, CA-11 | E11 | | |
| RF-14 | — | — | CA-14 | E06 | | |
| RF-15 | — | FL-01, FL-04, FL-05 | CA-24 | E12 | | |
| RF-16 | RN-10, RN-11 | — | CA-03, CA-13 | E05 | | |
| RNF-01 / [autenticação](../05-seguranca-e-privacidade/autenticacao.md) | — | — | CA-15 | E01 | | |

Todo RF tem ao menos um critério de aceite desde a versão 2.5.

> **Entrega da Etapa 4, sub-etapa A1 (privacidade e auditoria):** cobre **RF-04 / CA-16** (termos versionados, `GET /privacy/terms` com o texto integral, consentimento com evidência de aceite e aceite obrigatório no cadastro) e **RF-16 / CA-13** (exercício de direitos com protocolo e trilha de auditoria consultável). As colunas "Histórias" e "Testes" só serão preenchidas quando o backlog for detalhado (entregável 1); escopo e dívidas da sub-etapa estão no [ADR-0030](../06-arquitetura/decisoes/ADR-0030-privacidade-e-auditoria.md).
