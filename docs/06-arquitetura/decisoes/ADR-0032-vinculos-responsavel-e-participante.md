# ADR-0032 — Vínculos responsável↔participante e cadastro do participante (Etapa 5, E03)

**Status:** aceito · **Data:** 11/11/2026 · **Aprovado por:** Heles Júnior (a confirmar) · **Resolve:** E03 (em parte); relaciona ADR-0003, ADR-0005, ADR-0007, ADR-0012, ADR-0013, ADR-0015, ADR-0018, ADR-0026, ADR-0027, ADR-0029

## Contexto
A Etapa 5 entrega o **cadastro único do participante** e os **vínculos do responsável** (E03). Até aqui a entidade `participante` existia no modelo e no schema, mas sem o fluxo de cadastro, sem vínculo real com o responsável, sem elegibilidade etária aplicada, sem dedup/mesclagem e sem o pipeline de arquivos. Esta ADR registra as decisões estruturais da entrega e as dívidas que ficam fora.

## Decisão

1. **Vínculo em duas tabelas.** `responsavel_participante` guarda a **situação corrente** do vínculo (`validacao`: `pendente`, `aprovada`, `aguardando_documento`, `recusada`; vigência em `inicio`/`fim`); `solicitacao_vinculo` é o **log append-only** (solicitação, contestação, revogação). Encerrar um vínculo preenche `fim` e **não** apaga o log. O gate de acesso é `validacao = aprovada AND fim IS NULL`.
2. **Autorização do responsável por propriedade.** O responsável acessa o dependente pelo vínculo (não por território nem por conhecer a matrícula): `VerificadorPropriedadeParticipante` e `GET /me/participants`. A leitura territorial (`GET /participants/{id}` sob `participante.ler`) continua para operadores; o responsável sem vínculo recebe 403 (CA-04). As escritas contra um participante (`POST /participants/{id}/guardians`, `POST /participants/{id}/age-exceptions`, `POST /participants/{id}/merge[/undo]`) e as leituras identificáveis combinam **propriedade** (vínculo `aprovada` e vigente) **ou** território (operação do papel cobrindo a igreja de referência, resolvida à EBI quando há uma) — regra em `App\Support\Participantes\AutorizacaoParticipante`.
3. **`responsavel_concordante_id` referencia `responsavel_participante`** (não `usuario`), e é anulável — a concordância pode vir de responsável sem conta (ADR-0015).
4. **Elegibilidade etária aplicada (RN-16, CA-17, CA-19).** `AvaliadorElegibilidade` decide a idade na **data local da igreja** (fuso), com 29/02 completando ano em 01/03 nos anos não bissextos: `< 3` bloqueado sempre (mesmo com exceção, ADR-0013); `3..11` permitido; `>= 12` só com exceção `aprovada` e vigente. A exceção inclusiva é decidida pela coordenadora (ADR-0012).
5. **Dedup + mesclagem reversível (CA-27, ADR-0007).** Detecção por nome normalizado (sem acento, minúsculas) + data de nascimento; mesclar funde o duplicado no principal, move os vínculos e marca o duplicado como `mesclado`, guardando o **snapshot** do estado anterior em `participante.snapshot_mesclagem`. Desfazer restaura o snapshot, **devolve os vínculos movidos ao duplicado** e o reativa (`ativo`). Toda ação é auditada. A mesclagem é gateada pelo **escopo territorial** sobre a igreja de referência de **ambos** os cadastros (design §6.7), via `AutorizacaoParticipante`.
6. **Cadastro assistido (FL-01, CA-16).** A coordenadora cadastra com **evidência de aceite obrigatória** (arquivo que passa pelo pipeline de storage). O responsável do cadastro existe **sem conta** (`usuario_id` nulo) e o vínculo nasce `aprovada` (foi a própria coordenadora que cadastrou). O consentimento é registrado com **usuário nulo** e `origem = assistido`, referenciando o participante.
7. **Pipeline de storage (E08, ADR-0005).** `App\Support\Arquivo`: o upload vai para a **quarentena** (sem cifra), um job valida o **tipo real** pela assinatura de bytes (PDF/JPEG/PNG), roda o **antivírus** (clamd) e, se limpo, promove para o bucket `laudos` (**privado e cifrado** — SSE-S3 AES256). Infectado/inválido é apagado da quarentena e auditado. `arquivo.status_antivirus` registra o veredito.
8. **Leitura identificável com finalidade declarada (`*` da matriz).** As leituras identificáveis de participante exigem a `finalidade` (query `finalidade` ou header `X-Finalidade`) e gravam `evento_auditoria` (`acao = participante.lido`, finalidade em `motivo`). A **finalidade de leitura** (matriz) **não** reusa a finalidade do termo de consentimento. A exceção completa do [ADR-0029](ADR-0029-dados-identificaveis-acima-da-igreja.md) (nomeação + prazo ≤ 30 dias + aprovação do DPO) fica como dívida.
9. **Matrícula (RF-02, CA-25).** 8 caracteres em base32 Crockford + dígito verificador, gerada com retry em colisão; nunca derivada de sequência/data; imutável e nunca credencial.

## Dívidas (explicitamente fora desta entrega)
1. **Exceção do ADR-0029 completa** (finalidade nomeada + prazo ≤ 30 dias + aprovação do DPO) para leituras acima da igreja — proposta, aguarda CCB/DPO. A entrega atual registra a finalidade declarada e a audita.
2. **`*` nas demais leituras** (fora de participante) — mesma dependência de DPO.
3. **Notificação ao responsável antes do 12º aniversário (RF-15).**
4. **Anexo de laudo da exceção (FL-06)** e **concordância do adulto com deficiência** (ADR-0019, pendente do jurídico).
5. **Reuso do pipeline de storage pela E08** (biblioteca de atividades) — a superfície `ArmazenarArquivo` está pronta para isso.
6. **Escopo territorial fino da mesclagem por presença (E04)** — o gate cobre a igreja de referência; a mesclagem por presença/participação em atividade fica para E04.
7. **Evidência do cadastro assistido rejeitada após o cadastro (M5).** A evidência é enfileirada antes de criar o participante; a fila é assíncrona em produção, então o arquivo pode ser classificado `infectado`/`inválido` **depois** de o participante existir. Falta a reconciliação/auditoria que sinaliza o cadastro assistido sem evidência limpa. Fica para a fase devida (E08/retenção).
8. **`PATCH /participants/{id}` (correção) sem rota e `GET /participants` sem paginação (M9).** A operação `participante.corrigir` está no catálogo/matriz, mas sem endpoint nesta entrega; a busca/dedup devolve a lista inteira do escopo (sem cursor/limite).
9. **`aprovar` exceção aceita `vigente_ate` no passado (M8).** A exceção já-vencida não concede presença (o `AvaliadorElegibilidade` recusa), mas a aprovação não valida a data de vigência na entrada; regra de sanidade fica para o hardening.
10. **Cifra client-side vs SSE-S3** — usado SSE-S3 (MinIO); chave gerenciada pela aplicação fica como alternativa se a CCB exigir.
11. **Testes de frontend (Vitest)** — segue validado por `lint` + `build` (ADR-0022).

## Consequências
- A matriz de autorização ganha as operações `participante.cadastrar/corrigir/mesclar`, `vinculo.gerenciar`, `excecao.decidir/ler` e `evidencia.ler` ([matriz](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md)).
- O cadastro assistido passa a ser **bloqueante sem evidência**: sem arquivo válido (limpo no pipeline), o cadastro não conclui.
- A idade deixa de ser só conceitual: a elegibilidade é aplicada e testada (virada de dia/fuso, 29/02, véspera/dia dos 12, exceção expirada).
- O OpenAPI ganha 12 operações de participante/arquivo (60 no total); `endpoints.md` marca o que ainda não está no contrato.
- As dívidas acima precisam de dono na fase devida: DPO/CCB para o `*`; jurídico para a concordância do adulto; E04/E08/RF-15 para o resto.