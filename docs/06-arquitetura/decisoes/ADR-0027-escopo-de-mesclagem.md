# ADR-0027 — Escopo da coordenadora para mesclar e corrigir cadastros

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-22

## Contexto
O [ADR-0007](ADR-0007-cadastro-e-correcao-de-participantes.md) deu à coordenadora o poder de mesclar e corrigir cadastros, sem dizer até onde. Sem limite, qualquer coordenadora alteraria cadastros do país inteiro.

## Decisão
- A coordenadora só mescla ou corrige participantes que tenham **presença ou igreja de referência em um EBI onde ela atua**.
- Casos fora desse escopo (por exemplo, duplicidade entre participantes de EBIs diferentes) vão para a **coordenadora chefe**.
- Toda mesclagem e correção continua auditada e reversível.

## Consequências
- A [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) aplica o vínculo com o EBI nessas operações.
- Novo critério de aceite [CA-27](../../01-requisitos/criterios-de-aceite.md#ca-27).
