# Convenções da API

**Status:** proposto

- REST versionado em `/api/v1`, HTTPS, JSON (UTF-8).
- Rotas e campos em inglês; domínio e documentação em português. Glossário de tradução abaixo.
- IDs opacos (UUID/ULID). A matrícula nunca serve como credencial.
- Autorização por recurso em todo endpoint ([matriz de autorização](../05-seguranca-e-privacidade/matriz-de-autorizacao.md)). O contexto de atuação vai no cabeçalho `X-Acting-Scope` e é revalidado no servidor.
- Paginação por cursor (`?cursor=&limit=`), `limit` máximo 100.
- Filtros por query string; ordenação com `?sort=campo,-outro`.
- Datas em ISO 8601 com fuso. Datas de sessão também trazem o fuso da igreja.
- Erros no formato `application/problem+json` (RFC 9457), com `type`, `title`, `status`, `detail` e `code` interno.
- Escritas críticas (presença, utilização, avaliação, consentimento) aceitam `Idempotency-Key`.
- Upload de PDF por URL pré-assinada; o antivírus confirma por callback antes da publicação.
- Rate limit por usuário e por IP, com `429` e `Retry-After`.
- Especificação em [openapi.yaml](openapi.yaml) (OpenAPI 3.1), validada no CI e com testes de contrato.

## Tradução de termos

| Domínio | API |
|---|---|
| participante | participant |
| responsável | guardian |
| igreja | church |
| regional / setor | region / sector |
| sessão | session |
| presença | attendance |
| escala | schedule |
| exceção inclusiva | age-exception |
| atividade / versão | activity / version |
| utilização | activity-use |
| avaliação | rating |

## A detalhar
Payloads, códigos HTTP por rota, autenticação (tokens e refresh), especificação de eventos assíncronos e política de versionamento e depreciação.
