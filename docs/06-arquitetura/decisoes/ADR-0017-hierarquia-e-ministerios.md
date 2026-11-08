# ADR-0017 — Hierarquia da CCB e ministérios por nível

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-01

## Decisão
A hierarquia é:

```mermaid
flowchart LR
  C["Central da CCB (Brás)"] --> R["Regional<br/>Ministério Regional"] --> S["Setor<br/>Ministério Setorial"] --> I["Igreja<br/>Ministério Local"] --> E["EBI<br/>Coordenadoras e Auxiliares"]
```

- Cada nível tem seu ministério: **Ministério Regional**, **Ministério Setorial** e **Ministério Local**.
- No EBI atuam as **coordenadoras** (responsáveis) e as **auxiliares**.
- Hoje cada igreja tem no máximo um EBI. O sistema **permite mais de um EBI por igreja**, porque isso pode acontecer na Central (Brás), que tem estrutura para isso.

## Consequências
- O nível nacional passa a se chamar **Central (Brás)** nos documentos e telas; a entidade `central_nacional` continua sendo a raiz da hierarquia.
- Novos perfis **Ministério Regional** e **Ministério Setorial**, além do Ministério Local já criado ([ADR-0008](ADR-0008-moderacao-de-feedback.md)). Responde em parte a pergunta sobre as visões "Setorial" e "Regional" do app único ([ADR-0009](ADR-0009-aplicativo-unico.md)).
- **Proposta a confirmar:** os ministérios têm a visão de acompanhamento do seu nível (indicadores e consultas autorizadas). Os perfis de Administrador continuam como função operacional (cadastrar igrejas, usuários, EBIs). Se forem as mesmas pessoas, os perfis podem ser unificados.
- Falta definir se a Central tem um ministério próprio com visão nacional.
