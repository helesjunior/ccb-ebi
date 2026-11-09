# Critérios de aceite

Cenários essenciais que precisam passar antes do piloto. Cada critério tem ID estável e aponta para o requisito ou regra que valida. Na versão anterior a numeração repetia 6 e 7; a correspondência está na última coluna.

| ID | Cenário | Valida | Era (v1) |
|---|---|---|---|
| <a id="ca-01"></a>CA-01 | **Cadastro único:** participante já cadastrado frequenta outra igreja; o sistema reutiliza a mesma matrícula e não cria outro registro. | RF-02, RN-01 | 1 |
| <a id="ca-02"></a>CA-02 | **Presença idempotente:** dois envios simultâneos para o mesmo participante e sessão resultam em uma só presença válida. | RF-08, RN-03 | 2 |
| <a id="ca-03"></a>CA-03 | **Escopo:** administrador da igreja A não consegue consultar participantes ou feedbacks da igreja B fora das permissões e finalidades autorizadas. | RF-05, RF-16, RN-11 | 3 |
| <a id="ca-04"></a>CA-04 | **Responsável:** usuário sem vínculo validado não acessa dados do participante, mesmo conhecendo matrícula ou URL. | RF-03, RF-13, RN-05 | 4 |
| <a id="ca-05"></a>CA-05 | **Idade:** participante de 3 anos ou mais, sem exceção ativa, registra presença até a véspera do 12º aniversário; no dia do 12º aniversário a presença é bloqueada. Com exceção aprovada e vigente, a presença é permitida em qualquer idade. | RF-08, RN-13, RN-14 | 5 |
| <a id="ca-06"></a>CA-06 | **Privacidade da exceção:** usuário sem autorização específica não acessa diagnóstico, laudo ou justificativa sensível. | RF-02, RN-14 | 6 |
| <a id="ca-07"></a>CA-07 | **Localização:** com GPS desabilitado, a auxiliar seleciona a igreja manualmente e registra presença normalmente. | RF-08, RN-04 | 7 |
| <a id="ca-08"></a>CA-08 | **Biblioteca:** PDF enviado passa por validação; material bloqueado não fica disponível. | RF-10 | 6 (repetido) |
| <a id="ca-09"></a>CA-09 | **Reutilização:** baixar PDF não altera o contador; vincular uma versão a sessão realizada soma uma utilização. | RF-11, RN-06, RN-07 | 7 (repetido) |
| <a id="ca-10"></a>CA-10 | **Avaliação:** usuário habilitado avalia de 1 a 5; mudar a nota não cria uma segunda avaliação ativa. | RF-12, RN-08 | 8 |
| <a id="ca-11"></a>CA-11 | **Feedback:** responsável só vê observação de dependente vinculado depois de moderada e liberada pelo Ministério Local; feedback pendente não aparece. | RF-09, RF-13 | 9 |
| <a id="ca-12"></a>CA-12 | **Escalas:** conflito de horários é sinalizado antes da confirmação. | RF-07 | 10 |
| <a id="ca-13"></a>CA-13 | **Auditoria:** estorno de presença preserva o registro original, motivo, autor e data. | RF-08, RF-16, RN-10 | 11 |
| <a id="ca-14"></a>CA-14 | **Relatório nacional:** participante presente em duas igrejas conta como um participante distinto no total nacional e como duas presenças. | RF-14, M-02, M-03 | 12 |
| <a id="ca-15"></a>CA-15 | **MFA:** usuário de equipe ou administração sem segundo fator validado não acessa a área operacional. | RNF-01, [autenticação](../05-seguranca-e-privacidade/autenticacao.md#2-equipes-administradores-e-consulta) | 13 |
| <a id="ca-16"></a>CA-16 | **Consentimento:** versão do termo e evidência de manifestação podem ser recuperadas por operador autorizado. | RF-04 | 14 |
| <a id="ca-17"></a>CA-17 | **Exceção revogada ou expirada:** participante com 12 anos ou mais, cuja exceção foi revogada ou passou de `vigente_ate`, tem nova presença bloqueada; presenças anteriores continuam visíveis no histórico. | RF-02, RF-08, RN-15 | novo |
| <a id="ca-18"></a>CA-18 | **Transferência de igreja:** depois que a igreja muda de setor, todos os relatórios — inclusive de períodos anteriores — passam a atribuir os dados daquela igreja ao novo setor; a igreja é a âncora e o setor é sempre o vigente. | RF-01, RN-09 | novo |
| <a id="ca-19"></a>CA-19 | **Menores de 3 anos:** a presença de participante com menos de 3 anos na data da sessão é bloqueada, mesmo com exceção inclusiva registrada. | RF-08, RN-13 | novo (2.2) |
| <a id="ca-20"></a>CA-20 | **Aprovação de escala:** escala sem aprovação da coordenadora chefe, ou abaixo da equipe mínima, não aparece como confirmada e não conta na cobertura (M-06). | RF-07 | novo (2.2) |
| <a id="ca-21"></a>CA-21 | **Cadastro assistido:** coordenadora cadastra participante e responsável sem e-mail; a matrícula é gerada, o cadastro fica marcado como assistido e a evidência de consentimento é exigida. | RF-02, RF-03 | novo (2.2) |
| <a id="ca-22"></a>CA-22 | **Exceção pela coordenadora:** coordenadora registra exceção para participante de 12 anos ou mais, com o responsável indicado; a partir daí a presença é aceita em qualquer EBI; o registro guarda quem decidiu e quem concordou. | RF-02, RN-14 | novo (2.2) |
| <a id="ca-23"></a>CA-23 | **Sessão duplicada e correção:** criar uma segunda sessão no mesmo EBI e intervalo é bloqueado; corrigir uma sessão encerrada exige perfil autorizado e motivo, e fica na auditoria. | RF-06 | novo (2.5) |
| <a id="ca-24"></a>CA-24 | **Notificações:** usuário que desativou um tipo de notificação não a recebe; códigos de verificação enviados não ficam legíveis no banco nem nos logs. | RF-15 | novo (2.5) |
| <a id="ca-25"></a>CA-25 | **Matrícula:** em 10.000 matrículas geradas não há duas iguais nem relação entre matrículas consecutivas; uma matrícula com um caractere trocado é rejeitada pelo dígito verificador. | RF-02 | novo (2.5) |
| <a id="ca-26"></a>CA-26 | **Cálculo da idade:** participante nascido em 29/02 completa ano em 01/03 nos anos não bissextos; a idade usa a data local da sessão no fuso da igreja. | RF-08, RN-16 | novo (2.11) |
| <a id="ca-27"></a>CA-27 | **Escopo de mesclagem:** coordenadora não consegue mesclar nem corrigir participante sem presença ou igreja de referência em EBI onde atua; a coordenadora chefe consegue. | RF-02 | novo (2.11) |
