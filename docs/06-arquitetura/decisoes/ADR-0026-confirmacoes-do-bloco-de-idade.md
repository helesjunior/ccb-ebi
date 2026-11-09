# ADR-0026 — Confirmações do bloco de idade

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-21

## Decisão
1. **Cadastro antecipado:** é permitido cadastrar menores de 3 anos; só a presença fica bloqueada até o 3º aniversário ([ADR-0013](ADR-0013-bloqueio-menores-de-3-anos.md)).
2. **Validade da exceção inclusiva:** a exceção aprovada vale para as sessões seguintes, em qualquer EBI, até ser revogada (ou até `vigente_ate`, se preenchido). A coordenadora de cada sessão pode recusar a participação naquele dia.
3. **RN-15:** exceção `suspensa`, `revogada` ou `expirada` bloqueia novas presenças fora da faixa regular; histórico e auditoria são mantidos.
4. **RN-16:** a idade é calculada na data local da sessão, no fuso da igreja; nascidos em 29/02 completam ano em 01/03 nos anos não bissextos.

## Consequências
- O bloco de [elegibilidade etária](../../02-regras-de-negocio/elegibilidade-etaria.md) fica todo `aprovado` pelo produto, e o registro de presença do MVP (E04) não tem mais pendência de regra.
- Continua com o jurídico apenas a concordância do participante adulto ([ADR-0019](ADR-0019-base-normativa-lgpd.md)), que não muda o cálculo de elegibilidade.
- Novo critério de aceite [CA-26](../../01-requisitos/criterios-de-aceite.md#ca-26) para o cálculo de idade.
