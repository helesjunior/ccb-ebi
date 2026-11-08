# Autenticação

**Status:** proposto

## 1. Responsáveis
- Cadastro com e-mail único verificado e senha forte; funções sensíveis só depois da verificação.
- Recuperação de senha com token de uso único e validade curta; limite de tentativas e proteção contra enumeração de contas.
- MFA opcional, com autenticação reforçada em operações de maior risco (por exemplo, vincular ou alterar responsável).

## 2. Equipes, administradores e consulta
Vale para **todo perfil que não seja Responsável**, incluindo os perfis de Consulta (antes não estava explícito).

- Login com e-mail e senha ou identidade federada da CCB, se houver.
- **MFA obrigatório** ([CA-15](../01-requisitos/criterios-de-aceite.md#ca-15)):
  - TOTP compatível com Google Authenticator e similares, ou passkeys (preferidos para administradores);
  - código por e-mail como contingência controlada;
  - WhatsApp só após análise de risco, por ser mais vulnerável a sequestro de canal e depender de terceiros;
  - códigos de recuperação de uso único.
- Expiração de sessão, revogação de dispositivos, bloqueio progressivo, alertas de acesso e reautenticação para operações críticas.

## 3. Armazenamento de credenciais
- Senhas com Argon2id (ou equivalente recomendado), nunca de forma reversível.
- Segredos TOTP cifrados.
- Tokens e códigos nunca em log nem em texto claro no banco.

## A definir
- Duração de sessão por perfil e por canal (Web, app).
- Política de senha (tamanho mínimo, verificação contra senhas vazadas).
- Lista de "operações críticas" que pedem reautenticação.
