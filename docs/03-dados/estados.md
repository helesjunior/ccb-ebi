# Ciclos de vida

Estados e transições das entidades com regras de negócio. Toda transição gera evento de auditoria ([RN-10](../02-regras-de-negocio/regras-de-negocio.md#rn-10)).

## Exceção inclusiva

Definido em [FL-06](../01-requisitos/fluxos/FL-06-excecao-inclusiva.md). Estados: `solicitada`, `aprovada`, `recusada`, `suspensa`, `revogada`, `expirada`. A coordenadora faz todas as transições, com o responsável. Só `aprovada` dentro de `vigente_ate` (quando houver) permite presença depois dos 12 anos; abaixo de 3 anos nenhuma exceção vale.

## Sessão

```mermaid
stateDiagram-v2
  [*] --> prevista: gerada pela agenda
  prevista --> aberta: abertura
  prevista --> cancelada: cancelamento com motivo
  aberta --> encerrada: encerramento (marca realizada sim/não)
  encerrada --> encerrada: correção autorizada com motivo
  cancelada --> [*]
  encerrada --> [*]
```

## Presença

```mermaid
stateDiagram-v2
  [*] --> valida: registro
  valida --> estornada: estorno com motivo
  estornada --> [*]
```

Um estorno não apaga o registro. Depois dele, um novo registro `valida` pode ser criado para a mesma sessão ([RN-03](../02-regras-de-negocio/regras-de-negocio.md#rn-03)).

## Vínculo de responsável

```mermaid
stateDiagram-v2
  [*] --> solicitado
  solicitado --> aguardando_documento: coordenadora pede comprovação
  aguardando_documento --> ativo: documento conferido
  aguardando_documento --> recusado
  solicitado --> ativo: aprovado
  solicitado --> recusado
  ativo --> contestado: contestação
  contestado --> ativo: mantido
  contestado --> revogado: revogado
  ativo --> revogado: revogação ou decisão judicial
  ativo --> encerrado: fim da vigência
```

## Feedback

```mermaid
stateDiagram-v2
  [*] --> rascunho
  rascunho --> em_revisao: enviado
  em_revisao --> liberado: aprovado pela moderação
  em_revisao --> rascunho: devolvido para correção
  liberado --> contestado: contestação do responsável
  contestado --> liberado: mantido ou corrigido
  contestado --> retirado
  liberado --> retirado
```

A moderação é obrigatória e feita pelo Ministério Local ([ADR-0008](../06-arquitetura/decisoes/ADR-0008-moderacao-de-feedback.md)); não há atalho de `rascunho` para `liberado`.

## Escala

```mermaid
stateDiagram-v2
  [*] --> prevista: criada pela coordenadora
  prevista --> aprovada: coordenadora chefe aprova (equipe mínima atingida)
  aprovada --> confirmada: membro confirma
  confirmada --> substituida: troca aprovada pela coordenadora chefe
  confirmada --> ausencia: não compareceu
  confirmada --> cumprida: compareceu
```

Ver [ADR-0010](../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md).

## Atividade

```mermaid
stateDiagram-v2
  [*] --> enviada
  enviada --> bloqueada: falha técnica ou antivírus
  enviada --> em_moderacao: validação técnica ok
  em_moderacao --> publicada: aprovada
  em_moderacao --> recusada
  publicada --> retirada: infração ou inadequação
  publicada --> publicada: nova versão
```
