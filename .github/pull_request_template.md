# Descrição

<!-- O que muda e por quê. Contexto suficiente para um revisor externo. -->

## RF/CA atendidos

<!-- Liste os requisitos funcionais (RF) ou critérios de aceite (CA) atendidos.
     Se não houver, escreva "n/a". -->

- RF/CA:

## Como testar

<!-- Passos para reproduzir/validar manualmente, se aplicável. -->

## Checklist

- [ ] Testes passam (`docker compose run --rm app composer test`)
- [ ] Pint sem alterações (`docker compose run --rm app composer pint --test`)
- [ ] PHPStan sem erros (`docker compose run --rm app composer phpstan`)
- [ ] Frontend: lint e build (`npm run lint` e `npm run build` em `frontend/`)
- [ ] Documentação atualizada quando o comportamento mudou (docs, OpenAPI, CHANGELOG)
- [ ] Manual do usuário atualizado em `docs/wiki/` quando houver funcionalidade nova ou fluxo
      alterado, com captura de tela nova (veja `docs/wiki/Manutencao-do-wiki.md`)
- [ ] Sem segredos no código ou em arquivos versionados

## PR de infraestrutura

<!-- Preencha apenas para mudanças em infra/, docker/ ou .github/. -->

- [ ] Captura de tela ou saída do `docker compose ps` mostrando os serviços saudáveis
- [ ] Impacto em produção descrito (imagens, variáveis, migrações, rollback)