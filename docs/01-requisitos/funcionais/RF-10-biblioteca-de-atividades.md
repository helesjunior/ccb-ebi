# RF-10 — Biblioteca compartilhada de atividades bíblicas

**Status:** proposto · **Épico:** [E08](../../07-produto/backlog-epicos.md) · **Fase:** 2

## Requisitos
1. Coordenadoras e auxiliares autorizadas enviam **PDFs** com título, descrição, livro/capítulo/versículos, tema, faixa etária recomendada, tags, autoria/créditos, Regional/Setor/Igreja de origem e data da apresentação original.
2. Origem territorial e data original são preservadas; edições geram nova versão.
3. Pré-visualização, pesquisa por texto e metadados, filtros por passagem bíblica, idade, tema, regional, setor, igreja, data, popularidade e avaliação.
4. Downloads sujeitos a regras de acesso ([matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md)) e licenciamento. Arquivos passam por antivírus, validação do tipo real e limites de tamanho, em armazenamento privado ([ADR-0005](../../06-arquitetura/decisoes/ADR-0005-armazenamento-e-antivirus.md)).
5. Revisão editorial/moderação antes da publicação: por padrão a publicação vale na própria Regional, aprovada por moderador regional; a nacional exige o Ministério da Central ([ADR-0028](../../06-arquitetura/decisoes/ADR-0028-politica-editorial-dos-pdfs.md), proposto).
6. Só material de autoria própria ou autorizado; registrar autoria e declaração de direitos de uso (uso interno, sem fins comerciais, com crédito ao autor). PDFs com fotos de crianças ou dados pessoais são recusados. Material denunciado por direitos autorais sai do ar na hora, até a análise.

## Relacionados
- Regras: [RN-12](../../02-regras-de-negocio/regras-de-negocio.md#rn-12)
- Fluxos: [FL-03](../fluxos/FL-03-publicacao-e-reutilizacao.md)
- Critérios de aceite: [CA-08](../criterios-de-aceite.md#ca-08)
- Dados: `atividade`, `atividade_versao`, `arquivo`, `atividade_tag`, `denuncia` ([estados](../../03-dados/estados.md#atividade))
- API: `GET/POST /activities`, `POST /activities/{id}/versions`, `POST /activities/{id}/reports`

## Pendências
- [D-06](../../06-arquitetura/decisoes/README.md): a regra do [ADR-0028](../../06-arquitetura/decisoes/ADR-0028-politica-editorial-dos-pdfs.md) aguarda validação da CCB e do jurídico (texto da licença, moderadores, prazo de análise).
