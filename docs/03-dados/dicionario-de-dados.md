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
| Demais | Pública interna ou Pessoal | A definir | |

## Modelo de detalhamento por campo

| Campo | Tipo | Obrigatório | Classe | Validação | Origem | Observação |
|---|---|---|---|---|---|---|
| `participante.matricula` | texto (opaco) | sim | Pessoal de criança | 8 caracteres em base32 Crockford + 1 dígito verificador, nunca derivados de sequência, data ou dado pessoal; unicidade nacional ([RF-02](../01-requisitos/funcionais/RF-02-cadastro-de-participantes.md), [CA-25](../01-requisitos/criterios-de-aceite.md#ca-25)) | sistema | imutável; nunca serve como credencial |
| `participante.data_nascimento` | data | sim | Pessoal de criança | não futura | responsável ou operador | base da RN-13 |
