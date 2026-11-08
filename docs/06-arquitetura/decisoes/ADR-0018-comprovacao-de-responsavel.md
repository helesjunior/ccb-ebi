# ADR-0018 — Comprovação de responsável só em caso de dúvida

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-03

## Decisão
- A legitimidade do responsável é comprovada por **certidão de nascimento** ou, quando houver guarda, por **documento judicial**.
- O documento **não é exigido no cadastro**. A coordenadora do EBI valida o cadastro e, **se tiver dúvida**, pede que o responsável apresente o documento.

## Consequências
- Novo passo no FL-01: validação do cadastro pela coordenadora, com a opção "pedir comprovação".
- O vínculo fica `solicitado` até a validação; com pedido de comprovação, fica `aguardando_documento`.
- **Proposta, para o DPO validar:** a coordenadora apenas confere o documento e registra que conferiu (tipo, data, quem conferiu). A cópia não é guardada, salvo em guarda judicial, quando pode ser preciso registrar restrições. Isso reduz dados sensíveis armazenados.
- Contestação e revogação de vínculo continuam com tratamento especial para decisões judiciais (RF-03).
