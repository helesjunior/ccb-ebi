# Manutenção do wiki

Regra do projeto: **toda funcionalidade nova chega com a sua página neste wiki.** Sem exceção — se
mudou o que o usuário vê ou faz, mudou o manual.

## Onde fica o quê

| Onde | O que é |
|---|---|
| `docs/wiki/` (repositório) | **Fonte de verdade.** As páginas e as imagens vivem aqui, versionadas com o código |
| GitHub Wiki do repositório | Publicação do que está em `docs/wiki/` |

A versão editada é a do repositório: ela passa por revisão no pull request, junto com o código.

## O que uma página tem

1. **Objetivo em uma frase** — para que serve a tela/funcionalidade.
2. **Quem usa** — perfis que veem aquela tela.
3. **Passo a passo numerado**, com nomes exatos dos botões e campos.
4. **Captura de tela real**, não protótipo.
5. **O que a tela não faz** e **dúvidas frequentes**, quando houver.
6. Links para as páginas vizinhas do fluxo.

## Capturas de tela

- Tire do ambiente de desenvolvimento (`http://localhost:8000`), com **dado de demonstração** —
  nunca dado real de criança.
- Nomeie pelo número da tela: `NN-nome-curto.png` (ex.: `04-escalas-chefe.png`).
- Salve em `docs/wiki/images/`.
- Recapture quando o layout mudar; imagem velha confunde mais que imagem faltando.

## Checklist do pull request

- [ ] Funcionalidade nova ou fluxo alterado?
- [ ] Página do wiki criada ou atualizada em `docs/wiki/`
- [ ] Captura nova, se a tela mudou
- [ ] Página indexada em `Home.md` e no [Mapa das telas](Mapa-das-telas)
- [ ] Links conferidos (nome do arquivo, sem `.md` no link do wiki)

## Publicar

1. No pull request, a alteração entra em `docs/wiki/`.
2. Depois do merge, copie o conteúdo de `docs/wiki/` para o repositório do wiki:

```bash
git clone https://github.com/helesjunior/ccb-ebi.wiki.git /tmp/ccb-ebi-wiki
cp -r docs/wiki/. /tmp/ccb-ebi-wiki/
cd /tmp/ccb-ebi-wiki
git add -A && git commit -m "Atualiza o manual" && git push
```

3. Abra a Wiki e confira a página publicada — imagem quebrada é o erro mais comum.

## Padrão das páginas

- Título de página com nome curto e estável; link entre páginas é `[Texto](Nome-da-pagina)`, **sem**
  `.md` e **sem** extensão na imagem: `![alt](images/arquivo.png)`.
- Português direto, na voz do usuário ("toque em **Aprovar**"), não na voz do sistema.
- Não descreva implementação: descreva o que a pessoa faz e por quê.
