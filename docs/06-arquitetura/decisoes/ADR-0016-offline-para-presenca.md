# ADR-0016 — Offline só para registro de presença

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-08

## Contexto
Igrejas podem ter internet instável no horário do culto. As opções eram: só online com lista em papel (A), offline só para presença (B) ou offline completo (C).

## Decisão
**Opção B.** O app registra presenças sem internet na sessão aberta e as envia quando a conexão volta. As demais funções exigem conexão.

## Como funciona
1. Ao abrir a sessão (com internet), o app baixa a lista de participantes que já frequentaram aquele EBI, com só os dados necessários para identificar (nome, matrícula, data de nascimento e situação da exceção).
2. Sem conexão, a presença fica numa fila local, com a hora do registro e uma chave de idempotência.
3. A elegibilidade etária é verificada no aparelho com os dados baixados e de novo no servidor ao sincronizar.
4. Ao sincronizar, o servidor resolve duplicidades (mesma pessoa e sessão) e rejeita presenças inválidas; a coordenadora vê o que foi rejeitado e por quê.
5. Participante que não está na lista baixada não pode ser registrado offline; a coordenadora anota e lança depois.

## Consequências
- Entra com o app, na fase 3 (épico E13). Nas fases 1 e 2, o portal Web usa a contingência em papel ([RNF-03](../../01-requisitos/nao-funcionais.md)).
- Dados de crianças ficam no celular: banco local cifrado, apagado ao encerrar a sessão ou após um prazo curto (proposta: 24 h), e ao sair da conta.
- Cadastro, feedback, escalas e biblioteca continuam exigindo internet.
- O tamanho da lista baixada e o prazo de retenção no aparelho devem passar pelo DPO (D-04).
