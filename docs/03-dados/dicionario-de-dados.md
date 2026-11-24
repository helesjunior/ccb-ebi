# Dicionário de dados

Classificação de sensibilidade e retenção por entidade. Os prazos são o padrão provisório do [ADR-0019](../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md), configuráveis e pendentes de aprovação do DPO. O detalhamento campo a campo (tipo, obrigatoriedade, validação) será feito junto com as migrações do banco, usando o modelo no fim desta página.

## Classes de sensibilidade

| Classe | Exemplo | Controles |
|---|---|---|
| Pública interna | Nome da igreja, tema da sessão | Acesso autenticado |
| Pessoal | Nome e e-mail de usuário | Escopo territorial; auditoria de exportação |
| Pessoal de criança | Nome, nascimento, presença do participante | Escopo + vínculo; minimização; supressão em relatórios |
| Sensível | Laudo, diagnóstico, saúde; possivelmente a própria participação religiosa | Módulo segregado; cifrado; acesso com finalidade registrada |
| Segredo | Hash de senha, segredo TOTP, tokens | Nunca em log; cifrado; sem exportação |

## Entidades

| Entidade | Classe mais alta | Retenção proposta | Observação |
|---|---|---|---|
| `participante` | Pessoal de criança | Anonimizado 2 anos após a última presença (provisório, [ADR-0019](../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md)) | Inativação, nunca exclusão silenciosa |
| `excecao_etaria` | Sensível | Junto com o participante | Justificativa funcional, sem diagnóstico |
| `dado_sensivel_participante` | Sensível | Apagado ao fim da exceção | Campo `prazo_retencao` por registro |
| `responsavel_participante` | Pessoal | Vigência + prazo legal | |
| `presenca` | Pessoal de criança | Junto com o participante | Anonimizar para estatística histórica |
| `feedback` | Pessoal de criança | 2 anos após a liberação | |
| `manifestacao_privacidade` | Pessoal | 5 anos após o fim do tratamento | Evidência de consentimento |
| `usuario` | Pessoal | Vigência + prazo legal | |
| `fator_mfa`, `codigo_recuperacao`, `sessao_login` | Segredo | Até revogação | |
| `evento_auditoria` | Pessoal | 5 anos; não apagável antes do prazo (logs de acesso: 6 meses) | Metadados minimizados |
| `atividade`, `atividade_versao`, `arquivo` | Pública interna | Enquanto publicada + histórico | Nunca contém dados de participantes (RN-12) |
| `escala`, `escala_membro` | Pessoal | Junto com a sessão | Quem foi escalado e quem esteve presente; não é dado de criança |
| `solicitacao_troca_escala` | Pessoal | Junto com a sessão | `motivo` pode conter dado pessoal livre: limita a 500 caracteres e não entra em relatório |
| `vinculo_acesso` (`chefe_de_ebi_id`) | Pessoal | Vigência + prazo legal | Diz quem é a coordenadora chefe do EBI ([ADR-0033](../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)) |
| Demais | Pública interna ou Pessoal | A definir | |

## Modelo de detalhamento por campo

| Campo | Tipo | Obrigatório | Classe | Validação | Origem | Observação |
|---|---|---|---|---|---|---|
| `participante.matricula` | texto (opaco) | sim | Pessoal de criança | 8 caracteres em base32 Crockford + 1 dígito verificador, nunca derivados de sequência, data ou dado pessoal; unicidade nacional ([RF-02](../01-requisitos/funcionais/RF-02-cadastro-de-participantes.md), [CA-25](../01-requisitos/criterios-de-aceite.md#ca-25)) | sistema | imutável; nunca serve como credencial |
| `participante.data_nascimento` | data | sim | Pessoal de criança | não futura | responsável ou operador | base da RN-13 |
| `participante.origem_cadastro` | enum | sim | Metadado | `responsavel` ou `assistido` ([ADR-0015](../06-arquitetura/decisoes/ADR-0015-cadastro-assistido.md)) | sistema | `assistido` exige `evidencia_consentimento_id` |
| `participante.evidencia_consentimento_id` | FK `arquivo` | quando `assistido` | Metadado | FK real (E03) | operador (upload) | só após o pipeline de storage promover o arquivo a `limpo` |
| `participante.status` | enum | sim | Pessoal de criança | `ativo`, `inativo`, `mesclado` | sistema/operador | `mesclado` marca o duplicado de uma mesclagem reversível |
| `participante.snapshot_mesclagem` | json | não | Metadado | estado anterior do principal + duplicado | sistema | usado apenas por desfazer a mesclagem |
| `arquivo.status_antivirus` | enum | sim | Metadado | `pendente`, `limpo`, `infectado`, `invalido` | sistema | decide a promoção ao bucket cifrado ([ADR-0005](../06-arquitetura/decisoes/ADR-0005-armazenamento-e-antivirus.md)) |
| `arquivo.cifrado` | booleano | sim | Metadado | verdadeiro só no bucket `laudos` (SSE) | sistema | quarentena sem cifra |
| `arquivo.sha256` | texto | não | Metadado | sha-256 do conteúdo promovido | sistema | integridade |
| `vinculo_acesso.chefe_de_ebi_id` | FK `ebi` | não | Pessoal | único; só com papel `coordenadora_chefe` e escopo `ebi`; preenchido enquanto vigente e zerado ao encerrar ([ADR-0033](../06-arquitetura/decisoes/ADR-0033-coordenadora-chefe-e-regras-da-escala.md)) | sistema | a unicidade também é conferida no serviço, nos três caminhos de vínculo/convite; a violação do índice vira `422 chefe_ja_definida` |
| `escala.estimativa_participantes` | inteiro | sim | Pessoal | ≥ 0, digitado pela coordenadora | coordenadora | não é calculado; 0 → 0 auxiliares |
| `escala.participantes_por_auxiliar` | inteiro | sim | Metadado | ≥ 1, copiado do EBI na criação | sistema | muda o divisor do EBI não reescreve escalas existentes |
| `escala.status` | enum | sim | Metadado | `prevista`, `aprovada` | sistema | alteração depois de aprovada volta a `prevista` |
| `escala_membro.funcao` | enum | sim | Pessoal | `coordenadora`, `auxiliar` | coordenadora | |
| `escala_membro.status` | enum | sim | Pessoal | `escalado`, `confirmado`, `troca_solicitada`, `substituido` | sistema | unicidade de membro ativo por escala e horário é do serviço |
| `escala_membro.presente` | booleano | não | Pessoal | registrado na sessão `encerrada` | quem registra presença | `null` = ainda não conferido |
| `solicitacao_troca_escala.status` | enum | sim | Metadado | `pendente`, `aprovada`, `recusada`, `cancelada` | sistema | `expirada` é derivado na listagem, não gravado |

