# Matriz de autorização

**Status:** proposto · Base para o [entregável 5](../07-produto/entregaveis.md).

A implementação combina **RBAC** (papel) com **ABAC** (escopo territorial, vínculo ativo, finalidade e propriedade do recurso). Todo endpoint e toda consulta ao banco aplicam a autorização; esconder botões na interface não basta. Testes automáticos cobrem acesso horizontal (outra unidade do mesmo nível) e vertical (nível acima).

## Matriz inicial

Legenda: **G** gerir · **R** registrar · **L** ler · **A** agregado (sem identificar pessoas) · **—** sem acesso · **\*** só com finalidade registrada.

| Recurso | Super Admin | Adm. Regional | Adm. Setor | Adm. Igreja | Coordenadora | Auxiliar | Consulta (qualquer nível) | Responsável |
|---|---|---|---|---|---|---|---|---|
| Estrutura organizacional | G | G (regional) | G (setor) | L | L | L | L | — |
| Usuários e vínculos de acesso | G | G (regional) | G (setor) | G (igreja) | L (EBI) | — | — | — |
| Participante: identificação | L\* | L\* | L\* | L (igreja) | L (EBI) | L (EBI) | A | L (dependentes) |
| Participante: cadastro | G\* | — | — | G (igreja) | R | R | — | R (dependentes) |
| Exceção inclusiva: status | L\* | L | L | L | L | L | — | L (dependentes) |
| Exceção inclusiva: decisão | atribuição | atribuição | atribuição | — | — | — | — | solicita |
| Dado sensível (laudo) | — | atribuição\* | atribuição\* | — | — | — | — | L (dependentes) |
| Agenda e sessões | L | L | L | G | G (EBI) | L (EBI) | A | L (sessões do dependente) |
| Presença | L\* | L\* | L\* | L | R (EBI) | R (EBI) | A | L (dependentes) |
| Estorno de presença | — | — | — | R | R (EBI) | — | — | — |
| Escala | L | L | L | G | G (EBI) | L (própria) | A | — |
| Feedback | — | — | — | L | R (EBI) | R (EBI) | — | L (liberados, dependentes) |
| Biblioteca | G | moderação | moderação | L | R | R | L | — |
| Relatórios | A + L\* | A + L\* | A + L\* | A + L | A (EBI) | — | A | — |
| Auditoria | L | L (regional) | L (setor) | — | — | — | — | — |

"Atribuição" = função adicional concedida ao usuário (avaliador de exceção, moderador), ver [perfis](../00-visao/perfis-e-atores.md#perfis-a-definir).

## A validar
- A matriz é uma proposta para revisão; os níveis de acesso a dados identificáveis acima da igreja dependem de [D-10](../06-arquitetura/decisoes/README.md).
- Quem decide exceções e onde fica o laudo depende de [D-12](../06-arquitetura/decisoes/README.md).
