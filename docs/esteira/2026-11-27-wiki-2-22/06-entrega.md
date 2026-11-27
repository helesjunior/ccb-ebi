# Entrega

## Checklist do wiki (Manutencao-do-wiki.md)
- [x] Funcionalidade nova ou fluxo alterado?
- [x] Página do wiki criada ou atualizada em `docs/wiki/`
- [x] Captura nova, se a tela mudou
- [x] Página indexada em `Home.md` e no Mapa das telas — Home não muda (nenhuma tela nova)
- [x] Links conferidos (CT14 e CT15)

## Capturas
| Arquivo | Situação |
|---|---|
| 02-inicio-chefe.png | feita |
| 07-participantes-chefe.png | feita |
| 09-inicio-coordenadora.png | feita |
| 12-minhas-escalas-auxiliar.png | feita |
| 13-dependentes-responsavel.png | feita |
| 28-contingencia-lote.png | feita |

## Pendências
- Produto: o responsável não tem tela para ligar a própria conta a uma criança (ver 02-especificacao.md, Riscos). Na T8 o vínculo foi feito com `tinker`.

## Observações do ambiente local
- O SPA servido em `localhost:8000` era anterior à 2.22; foi preciso `npm ci` e `npm run build` no checkout montado no contêiner.
- Usuário com mais de um vínculo vigente recebe `422 acting_scope_obrigatorio` em `/api/v1/me` antes de escolher "Atuando como"; o script de captura enviou `X-Acting-Scope` para contornar.
- No seed, só "Patota (7 anos)" tem `nome_busca`; as demais crianças Patota só aparecem na busca da presença pela matrícula.
- A escala de 28/11 já existente no banco não tinha coordenação e não podia ser aprovada; a escala da T8 foi montada na sessão de 05/12, com a equipe da chefe (que já tinha o auxiliar).
