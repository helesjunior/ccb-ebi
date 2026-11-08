# Perfis e atores

O sistema tem 11 perfis de acesso, todos limitados a uma unidade territorial ou aos dependentes vinculados. A matriz detalhada de permissões está em [matriz de autorização](../05-seguranca-e-privacidade/matriz-de-autorizacao.md).

## Perfis

| Perfil | Abrangência | Permissões principais | MFA |
|---|---|---|---|
| Super Admin | Nacional | Configurações globais, estrutura, usuários, auditoria e governança; dados pessoais só com finalidade registrada | Obrigatório |
| Administrador de Regional | Regional designada | Gerir setores/igrejas, equipes, relatórios e parâmetros regionais | Obrigatório |
| Administrador de Setor | Setor designado | Gerir igrejas/EBIs, equipes e relatórios do setor | Obrigatório |
| Administrador de Igreja | Igreja designada | Gerir EBI, horários, equipe, escalas e dados operacionais locais | Obrigatório |
| Coordenadora EBI | EBI(s) autorizados | Planejar sessões, registrar presenças, feedbacks, atividades e escalas locais | Obrigatório |
| Auxiliar EBI | EBI(s) autorizados | Consultar escalas, registrar presenças, contribuir com atividades e feedbacks conforme autorização | Obrigatório |
| Consulta Nacional | Nacional, leitura | Indicadores agregados; dados identificáveis só com autorização específica | Obrigatório |
| Consulta da Regional | Regional, leitura | Indicadores e consultas autorizadas na regional | Obrigatório |
| Consulta do Setor | Setor, leitura | Indicadores e consultas autorizadas no setor | Obrigatório |
| Consulta da Igreja | Igreja, leitura | Indicadores e consultas autorizadas na igreja | Obrigatório |
| Responsável | Apenas dependentes vinculados | Cadastrar/atualizar dependentes, consentir, acompanhar frequência, conteúdos e feedbacks | Opcional, reforçado em operações de risco |

O perfil "Consulta Todas Regionais" da versão anterior passou a se chamar **Consulta Nacional**, alinhado ao nível "Central Nacional" da hierarquia.

## Perfis a definir

Os requisitos citam funções que ainda não têm perfil. Proposta: tratá-las como **atribuições** que se somam a um perfil existente, com escopo próprio.

| Atribuição | Citada em | Proposta | Status |
|---|---|---|---|
| Avaliador de exceção inclusiva | [RF-02](../01-requisitos/funcionais/RF-02-cadastro-de-participantes.md) | Atribuição concedida por regional ou setor; decide sobre exceções | Pendente de [D-12](../06-arquitetura/decisoes/README.md) |
| Moderador editorial | [RF-10](../01-requisitos/funcionais/RF-10-biblioteca-de-atividades.md), [RF-12](../01-requisitos/funcionais/RF-12-avaliacao-de-atividades.md) | Atribuição regional ou nacional; aprova e retira materiais | Pendente de [D-06](../06-arquitetura/decisoes/README.md) |
| Moderador de feedback | [RF-09](../01-requisitos/funcionais/RF-09-feedback.md) | Coordenadora do EBI ou atribuição específica | Pendente de [D-05](../06-arquitetura/decisoes/README.md) |
| Encarregado (DPO) | [RF-16](../01-requisitos/funcionais/RF-16-administracao-e-auditoria.md) | Atribuição nacional; atende solicitações LGPD com acesso registrado | Pendente de [D-04](../06-arquitetura/decisoes/README.md) |
| Suporte | [RF-16](../01-requisitos/funcionais/RF-16-administracao-e-auditoria.md) | Sem acesso a dados de participantes por padrão; acesso temporário com justificativa | Proposto |

## Regras transversais

- Um usuário pode acumular papéis e ter vínculos com mais de uma unidade. Ele escolhe o contexto de atuação ([RN-11](../02-regras-de-negocio/regras-de-negocio.md)).
- Toda permissão é verificada **no servidor** contra recurso, ação, unidade e vínculo ativo.
- Perfis de Consulta não dão acesso automático a nomes, contatos ou feedbacks individuais.
- O Super Admin não tem acesso irrestrito automático a conteúdo sensível; todo acesso a dado pessoal registra a finalidade.

## Linguagem de gênero

Os perfis "Coordenadora" e "Auxiliar" estão no feminino, como na versão anterior. Se for uma regra institucional, registrar. Se não, as telas devem usar forma neutra (por exemplo, "Coordenação EBI"). Pendente de confirmação com a CCB.
