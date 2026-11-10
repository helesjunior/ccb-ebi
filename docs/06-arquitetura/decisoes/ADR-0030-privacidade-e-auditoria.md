# ADR-0030 — Privacidade e auditoria (Etapa 4, sub-etapa A1): escopo e dívidas

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-04 (em parte); relaciona ADR-0019, ADR-0022, ADR-0029

## Contexto
A Etapa 3 ([ADR-0022](ADR-0022-identidade-e-acesso.md)) deixou a privacidade e a auditoria como dívida explícita de E05: não havia termos versionados, registro de consentimento, exercício de direitos do titular, consulta da trilha de auditoria, nem `ator_id` nas escritas auditadas. A Etapa 4 ataca isso em sub-etapas; esta ADR registra a **sub-etapa A1**, que entrega os mecanismos sobre dados **não sensíveis**, sem tocar na base normativa que ainda aguarda o jurídico e o DPO ([ADR-0019](ADR-0019-base-normativa-lgpd.md)). Os dados sensíveis com chave própria e a retenção ficam para a sub-etapa **A2**.

## Decisão
Entregar a sub-etapa A1 com o seguinte escopo:

1. **Termos versionados.** Tabela `termo_versao` e o núcleo `GerirTermos` (`vigente`, `publicar`, `encontrar`); publicar uma versão fecha a anterior, garantindo **uma versão vigente por finalidade**. Consulta em `GET /privacy/terms?finalidade=` (autenticado, sem gate), que devolve `{data: TermoVersao[]}`.
2. **Consentimento.** `ManifestacaoPrivacidade` (decisões **aceito**, **recusado** e **retirado**; origem **próprio** ou **assistido**), com `RegistrarConsentimento` e `ConsultarConsentimentos`. As tabelas `manifestacao_privacidade` e `solicitacao_titular` nascem na mesma migration. **Evidência de aceite** (`App\Support\EvidenciaAceite`) grava IP, User-Agent e o hash da versão do termo (CA-16).
3. **Rotas de consentimento.** `GET/POST /privacy/consents` para o próprio titular (sem gate) e `GET /privacy/consents/{id}` sob a operação `consentimento.ler` (super_admin, adm_regional, adm_setor, adm_igreja), com **checagem de subárvore do titular** (403 fora do escopo).
4. **Aceite obrigatório no cadastro.** O `register-guardian` exige o termo vigente válido e grava o consentimento **na mesma transação** do cadastro. O cadastro assistido completo permanece em E03.
5. **Direitos do titular.** `POST /privacy/requests` (acesso, correção, revogação) gera protocolo (RF-16).
6. **Auditoria consultável.** `GET /audit-events` sob a operação `auditoria.ler` (super_admin, adm_regional, adm_setor — **não** adm_igreja), com **filtro territorial por linha**: evento sem escopo territorial não aparece a papel territorial.
7. **`ator_id` nas escritas auditadas.** `GerenciadorUsuario`, `GerenciadorVinculo` e `ServicoTransferencia` passam a gravar o usuário atuante; a transferência passa a gravar escopo `setor:<id>`.
8. **Portal.** Telas de Privacidade e Auditoria.

E as seguintes dívidas ficam **explicitamente fora** da A1:

1. **Registro de finalidade (`*` da matriz) e exceção do [ADR-0029](ADR-0029-dados-identificaveis-acima-da-igreja.md).** Aguardam um recurso sensível para fazer sentido (e a definição de DPO/CCB).
2. **A2 — dados sensíveis com chave própria + retenção.** É a próxima sub-etapa da Etapa 4.
3. **Cadastro assistido completo (E03).** A A1 entrega o aceite obrigatório no cadastro; o fluxo assistido completo é de E03.
4. **Textos finais do termo.** Dependem do jurídico ([ADR-0019](ADR-0019-base-normativa-lgpd.md)).
5. **Testes de frontend.** O portal segue sem runner de testes (Vitest), validado por `lint` + `build` ([ADR-0022](ADR-0022-identidade-e-acesso.md)).

## Consequências
- A matriz de autorização ganha duas operações implementadas — `consentimento.ler` e `auditoria.ler` ([matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md)) —, com escopo territorial verificado por subárvore (consentimento) e por linha de evento (auditoria).
- O **aceite passa a ser bloqueante** no cadastro de responsável: sem termo vigente válido o cadastro não conclui, e a evidência fica registrada com IP, User-Agent e hash do termo (CA-16).
- A trilha de auditoria deixa de ser só escrita (RN-10): passa a ser **consultável**, com o `ator_id` ligando cada escrita auditada ao usuário que a praticou.
- As dívidas acima precisam de dono na fase devida: A2 para sensíveis e retenção; jurídico para os textos; E03 para o cadastro assistido.