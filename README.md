# EBI — Plataforma de Gestão do Espaço Bíblico Infantil

Plataforma Web + aplicativos Android/iOS + API para a gestão do Espaço Bíblico Infantil (EBI) da Congregação Cristã no Brasil (CCB): cadastro único de participantes, responsáveis, equipes, sessões, presenças, escalas, feedbacks, biblioteca de atividades em PDF e indicadores.

> **Situação:** especificação em validação (versão 2.0). Ainda não há código. Nada aqui presume autorização da administração nacional da CCB.

## Documentação

Toda a especificação está em [`docs/`](docs/README.md). Pontos de entrada:

| Quero… | Comece por |
|---|---|
| Entender o projeto | [Visão geral](docs/00-visao/visao-geral.md) |
| Ver o que o sistema faz | [Requisitos funcionais](docs/01-requisitos/funcionais/README.md) |
| Conhecer as regras de idade e exceção | [Elegibilidade etária](docs/02-regras-de-negocio/elegibilidade-etaria.md) |
| Ver o que ainda precisa ser decidido | [Decisões e ADRs](docs/06-arquitetura/decisoes/README.md) |
| Saber o que muda entre versões | [Changelog](docs/CHANGELOG.md) |

## Desenvolvimento

> **Situação:** fundação técnica em andamento (Etapa 0/1 do plano de desenvolvimento). Sem regra de negócio ainda.

Pré-requisito: Docker (Compose v2). **O PHP roda sempre em contêiner** (PHP 8.4 + FrankenPHP); não use o PHP do host.

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

Aplicação em `http://localhost:8000` (`/` = portal, `/api/v1/health` = estado dos serviços). Login de mentira da Etapa 1: `coordenadora@ebi.test` / `senha`.

Comandos úteis (backend no contêiner; frontend em `frontend/`):

```bash
docker compose run --rm app composer test      # suíte de testes
docker compose run --rm app composer pint      # formatação de código
docker compose run --rm app composer phpstan   # análise estática
docker compose exec scheduler php artisan sessoes:gerar --dias=28  # sessões previstas (o scheduler já roda diariamente)
```

O detalhamento do fluxo de contribuição, do contrato de API e das regras de PR está em [CONTRIBUTING.md](CONTRIBUTING.md).
