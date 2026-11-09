# Como contribuir

Obrigado pelo interesse na Plataforma EBI da CCB. Este documento descreve o
ambiente, os comandos do dia a dia e o fluxo de contribuição.

## Regra de ouro: PHP sempre em contêiner

O PHP do host **não** é usado. Todo comando de backend roda no contêiner da
aplicação (PHP 8.4 + FrankenPHP), via `docker compose run --rm app ...` ou
`docker compose exec app ...`. O host pode ter outra versão de PHP; não confie
nela.

## Pré-requisitos

- Docker (com Compose v2).
- Node.js 24+ apenas para o frontend fora do contêiner (opcional; o build de
  produção do portal também roda em contêiner).

## Subir o ambiente

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

A aplicação fica em `http://localhost:8000`:

- `GET /` — portal (SPA).
- `GET /up` — healthcheck simples.
- `GET /api/v1/health` — estado do banco e do Redis.
- `POST /api/v1/auth/login` — login de mentira da Etapa 1. Ator semeado:
  `coordenadora@ebi.test` / `senha`.

## Comandos do dia a dia

Backend (dentro do contêiner):

```bash
docker compose run --rm app composer test        # suíte Pest
docker compose run --rm app composer pint        # formata (use --test para conferir)
docker compose run --rm app composer phpstan     # análise estática (nível 8)
```

Frontend (na pasta `frontend/`):

```bash
npm install
npm run gen:api    # regenera o cliente a partir de docs/04-api/openapi.yaml
npm run lint       # oxlint
npm run build      # tsc + vite
```

OpenAPI e alinhamento de endpoints:

```bash
npx --yes @stoplight/spectral-cli lint docs/04-api/openapi.yaml --ruleset scripts/.spectral.yaml
docker run --rm -v "${PWD}:/work" -w /work python:3.12-alpine sh -c "pip install pyyaml -q && python scripts/check-endpoints.py"
```

Contrato antes do código: toda rota nova entra primeiro em
`docs/04-api/openapi.yaml` e é marcada como `no OpenAPI` em
`docs/04-api/endpoints.md`. O script `check-endpoints.py` falha se os dois
divergirem.

## Fluxo de contribuição

1. Crie uma branch a partir de `main` (`feat/...`, `fix/...`, `chore/...`).
2. Faça commits pequenos, com mensagem no imperativo em português.
3. Abra um Pull Request para `main` usando o modelo do repositório.
4. Garanta o CI verde (lint, estático, testes, Spectral, check-endpoints).
5. Aguarde **1 revisão** de um CODEOWNER e faça o merge pelo GitHub.

`main` é protegida: **só entra por PR**, exige CI verde e 1 revisão, e não
aceita force-push. A configuração da proteção no GitHub é manual (não há `gh`
CLI no ambiente do projeto) — veja a seção abaixo.

## Proteção do `main` (configuração manual no GitHub)

Em **Settings → Branches → Add branch protection rule** para `main`:

- Exigir Pull Request antes do merge (com pelo menos 1 aprovação).
- Exigir que as verificações de status do CI passem.
- Exigir que a branch esteja atualizada antes do merge.
- Bloquear force-push e exclusão da branch.

## Secret scanning

Em **Settings → Code security**: ativar **Secret scanning** e **Push
protection**. Isso impede o push de segredos detectáveis (chaves, tokens). O
Dependabot (`.`github/dependabot.yml`) também mantém as dependências atualizadas
e abre PRs automáticos.

## Segredos e configuração

- Segredos não vão para o git. Em produção, use arquivos de segredo via a
  convenção `*_FILE` (ex.: `DB_PASSWORD_FILE`), consumida por
  `App\Support\EnvArquivo`.
- Logs em produção vão para `stderr` em JSON (`LOG_CHANNEL=stderr`).