# Matriz de autorização

**Status:** proposto · Base para o [entregável 5](../07-produto/entregaveis.md).

A implementação combina **RBAC** (papel) com **ABAC** (escopo territorial, vínculo ativo, finalidade e propriedade do recurso). Todo endpoint e toda consulta ao banco aplicam a autorização; esconder botões na interface não basta. Testes automáticos cobrem acesso horizontal (outra unidade do mesmo nível) e vertical (nível acima).

## Matriz inicial

Legenda: **G** gerir · **R** registrar · **L** ler · **A** agregado (sem identificar pessoas) · **—** sem acesso · **\*** só com finalidade registrada.

| Recurso | Super Admin | Adm. Regional | Adm. Setor | Adm. Igreja | Ministério Local | Coordenadora | Auxiliar | Consulta e Ministério Regional/Setorial | Responsável |
|---|---|---|---|---|---|---|---|---|---|
| Estrutura organizacional | G | G (regional) | G (setor) | L | L | L | L | L | — |
| Usuários e vínculos de acesso | G | G (regional) | G (setor) | G (igreja) | — | L (EBI) | — | — | — |
| Participante: identificação | L\* | L\* | L\* | L (igreja) | L (igreja) | L (EBI) | L (EBI) | A | L (dependentes) |
| Participante: cadastro | G\* | — | — | G (igreja) | — | R, cadastro assistido | R | — | R (dependentes) |
| Participante: mesclar e corrigir | G\* | — | — | — | — | G (escopo a confirmar) | — | — | corrige dados permitidos |
| Exceção inclusiva: status | L\* | L | L | L | L | L | L | — | L (dependentes) |
| Exceção inclusiva: decisão | — | — | — | — | — | G (com o responsável) | — | — | solicita e concorda |
| Dado sensível (laudo) | — | — | — | — | — | L\* (EBI) | — | — | L (dependentes) |
| Agenda e sessões | L | L | L | G | L | G (EBI) | L (EBI) | A | L (sessões do dependente) |
| Presença | L\* | L\* | L\* | L | L | R (EBI) | R (EBI) | A | L (dependentes) |
| Estorno de presença | — | — | — | R | — | R (EBI) | — | — | — |
| Escala | L | L | L | G | L | G (EBI); aprova se chefe | L (própria) | A | — |
| Feedback | — | — | — | L | modera e libera | R (EBI) | R (EBI) | — | L (liberados, dependentes) |
| Biblioteca | G | moderação | moderação | L | L | R | R | L | — |
| Relatórios | A + L\* | A + L\* | A + L\* | A + L | A (igreja) | A (EBI) | — | A | — |
| Auditoria | L | L (regional) | L (setor) | — | — | — | — | — | — |

"Chefe" = atribuição de Coordenadora chefe ([ADR-0010](../06-arquitetura/decisoes/ADR-0010-equipe-minima-e-aprovacao-de-escala.md)). Mudanças da versão 2.2: coluna Ministério Local ([ADR-0008](../06-arquitetura/decisoes/ADR-0008-moderacao-de-feedback.md)); coordenadora mescla, corrige e faz cadastro assistido ([ADR-0007](../06-arquitetura/decisoes/ADR-0007-cadastro-e-correcao-de-participantes.md), [ADR-0015](../06-arquitetura/decisoes/ADR-0015-cadastro-assistido.md)); coordenadora decide a exceção com o responsável ([ADR-0012](../06-arquitetura/decisoes/ADR-0012-excecao-decidida-pela-coordenadora.md)). Versão 2.3: Ministério Regional e Setorial têm, por proposta, a mesma visão da Consulta do seu nível ([ADR-0017](../06-arquitetura/decisoes/ADR-0017-hierarquia-e-ministerios.md)).

## A validar
- A matriz é uma proposta para revisão; os níveis de acesso a dados identificáveis acima da igreja dependem de [D-10](../06-arquitetura/decisoes/README.md).
- Acesso da coordenadora ao laudo, quando existir: proposta de leitura só com finalidade registrada.
- Escopo da coordenadora para mesclar e corrigir cadastros (ADR-0007).
