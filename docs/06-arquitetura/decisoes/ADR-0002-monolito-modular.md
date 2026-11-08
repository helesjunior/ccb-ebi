# ADR-0002 — Monólito modular no início

**Status:** proposto · **Data:** 08/11/2026 · **Relaciona:** D-11

## Contexto
O sistema tem cerca de dez áreas de negócio, mas o piloto é regional e a capacidade operacional da TI da CCB ainda não é conhecida. Microsserviços aumentam custo de operação, observabilidade e implantação.

## Opções consideradas
1. **Monólito modular** — uma aplicação e um banco, módulos com fronteiras explícitas. Simples de operar; exige disciplina para manter as fronteiras.
2. **Microsserviços** — escala e implantação independentes; custo operacional alto para um piloto.

## Decisão
Proposta: começar com monólito modular, com os módulos da [arquitetura de referência](../arquitetura-de-referencia.md). Módulos só conversam por interfaces públicas; nenhum módulo lê tabela de outro diretamente.

## Consequências
- Separar um módulo depois (por exemplo, Notificações) continua possível.
- O módulo de dados sensíveis mantém isolamento lógico e chave própria mesmo dentro do monólito.
- Aguarda validação da TI da CCB (D-11).
