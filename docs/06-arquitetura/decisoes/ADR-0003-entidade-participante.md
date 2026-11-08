# ADR-0003 — Entidade `participante` no lugar de `crianca`

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior

## Contexto
A especificação chamava a entidade principal de `crianca` e a descrevia como "entidade legada", mantida mesmo para participantes acima de 12 anos com exceção inclusiva. Não existe sistema legado: o repositório começou vazio. Com a exceção sem limite de idade, a mesma entidade guarda adolescentes e adultos.

## Opções consideradas
1. Manter `crianca` — evita mudar o texto, mas o nome fica errado para parte dos registros e exige explicação permanente.
2. Renomear para `participante` agora — custo zero, pois não há código nem dados.

## Decisão
Usar `participante` em dados, telas e documentação, e `participant` na API. O termo "criança" fica restrito às regras que dependem de idade.

## Consequências
- Tabelas, rotas e telas usam o termo neutro desde o início.
- Entidades derivadas foram renomeadas: `responsavel_participante`, `dado_sensivel_participante`, `excecao_etaria`.
- Rotas `/children` e `/me/children` viraram `/participants` e `/me/participants`.
