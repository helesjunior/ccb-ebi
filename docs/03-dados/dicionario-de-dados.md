# Dicionário de dados

Classificação de sensibilidade e retenção por entidade. O detalhamento campo a campo (tipo, obrigatoriedade, validação) será feito junto com as migrações do banco, usando o modelo no fim desta página.

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
| `participante` | Pessoal de criança | A definir ([D-04](../06-arquitetura/decisoes/README.md)) | Inativação, nunca exclusão silenciosa |
| `excecao_etaria` | Sensível | A definir | Justificativa funcional, sem diagnóstico |
| `dado_sensivel_participante` | Sensível | Mínima necessária | Campo `prazo_retencao` por registro |
| `responsavel_participante` | Pessoal | Vigência + prazo legal | |
| `presenca` | Pessoal de criança | A definir | Anonimizar para estatística histórica |
| `feedback` | Pessoal de criança | A definir | |
| `manifestacao_privacidade` | Pessoal | Enquanto houver tratamento + prazo de prova | Evidência de consentimento |
| `usuario` | Pessoal | Vigência + prazo legal | |
| `fator_mfa`, `codigo_recuperacao`, `sessao_login` | Segredo | Até revogação | |
| `evento_auditoria` | Pessoal | A definir; não apagável antes do prazo | Metadados minimizados |
| `atividade`, `atividade_versao`, `arquivo` | Pública interna | Enquanto publicada + histórico | Nunca contém dados de participantes (RN-12) |
| Demais | Pública interna ou Pessoal | A definir | |

## Modelo de detalhamento por campo

| Campo | Tipo | Obrigatório | Classe | Validação | Origem | Observação |
|---|---|---|---|---|---|---|
| `participante.matricula` | texto (opaco) | sim | Pessoal de criança | 8 caracteres em base32 Crockford + 1 dígito verificador, nunca derivados de sequência, data ou dado pessoal; unicidade nacional ([RF-02](../01-requisitos/funcionais/RF-02-cadastro-de-participantes.md), [CA-25](../01-requisitos/criterios-de-aceite.md#ca-25)) | sistema | imutável; nunca serve como credencial |
| `participante.data_nascimento` | data | sim | Pessoal de criança | não futura | responsável ou operador | base da RN-13 |
