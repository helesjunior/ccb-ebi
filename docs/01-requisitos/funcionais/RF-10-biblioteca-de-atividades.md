# RF-10 — Biblioteca compartilhada de atividades bíblicas

**Status:** proposto · **Épico:** [E08](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Coordenadoras e auxiliares autorizadas enviam **PDFs** com título, descrição, livro/capítulo/versículos, tema, faixa etária recomendada, tags, autoria/créditos, Regional/Setor/Igreja de origem e data da apresentação original.
2. Origem territorial e data original são preservadas; edições geram nova versão.
3. Pré-visualização, pesquisa por texto e metadados, filtros por passagem bíblica, idade, tema, regional, setor, igreja, data, popularidade e avaliação.
4. Downloads sujeitos a regras de acesso ([matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md)) e licenciamento. Arquivos passam por antivírus, validação do tipo real e limites de tamanho, em armazenamento privado ([ADR-0005](../../06-arquitetura/decisoes/ADR-0005-armazenamento-e-antivirus.md)).
5. Revisão editorial/moderação antes da publicação; abrangência e responsáveis pendentes em [D-06](../../06-arquitetura/decisoes/README.md).
6. Registrar autoria, declaração de direitos de uso e retirada de material por infração ou inadequação.

## Relacionados
- Regras: [RN-12](../../02-regras-de-negocio/regras-de-negocio.md#rn-12)
- Fluxos: [FL-03](../fluxos/FL-03-publicacao-e-reutilizacao.md)
- Critérios de aceite: [CA-08](../criterios-de-aceite.md#ca-08)
- Dados: `atividade`, `atividade_versao`, `arquivo`, `atividade_tag`, `denuncia` ([estados](../../03-dados/estados.md#atividade))
- API: `GET/POST /activities`, `POST /activities/{id}/versions`, `POST /activities/{id}/reports`

## Pendências
- [D-06](../../06-arquitetura/decisoes/README.md): política editorial, direitos autorais e publicação nacional.
