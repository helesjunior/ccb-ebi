# Privacidade e LGPD

**Status:** proposto · Referência normativa: as normas aplicáveis à CCB como instituição filantrópica sem fins lucrativos ([ADR-0019](../06-arquitetura/decisoes/ADR-0019-base-normativa-lgpd.md)). Os itens que ainda dependem do jurídico e do encarregado estão listados nesse ADR.

## Pontos de atenção
1. **Dados de crianças e adolescentes.** O tratamento deve ser feito no melhor interesse (LGPD, art. 14). O consentimento específico de um dos pais ou responsável é uma hipótese, mas não a única: a ANPD admite outras bases legais dos arts. 7º e 11, desde que observado o melhor interesse (Enunciado CD/ANPD nº 1/2023). **Não presumir que consentimento é a base para todo tratamento.**
2. **Participação religiosa pode ser dado sensível** (art. 5º, II: "convicção religiosa"). Se for, valem as bases restritas do art. 11 e controles reforçados para toda a base de presenças, não só para laudos. As duas questões (bases legais para crianças e sensibilidade da participação) devem ser levadas juntas ao jurídico.
3. **Participante adulto com deficiência.** A CCB decidiu que não há limite de idade e que a coordenadora decide com o responsável ([ADR-0012](../06-arquitetura/decisoes/ADR-0012-excecao-decidida-pela-coordenadora.md)). A Lei Brasileira de Inclusão (Lei 13.146/2015) mantém a capacidade civil plena do adulto com deficiência; o jurídico deve definir como colher também a concordância do próprio participante.
4. **Cadastro assistido** ([ADR-0015](../06-arquitetura/decisoes/ADR-0015-cadastro-assistido.md)): definir a evidência de consentimento sem aceite digital.
5. **Adolescentes de 12 a 17 anos** com exceção inclusiva precisam de regra própria de consentimento e informação.

## Entregáveis de privacidade
- [ ] Inventário de dados e finalidades (base: [dicionário de dados](../03-dados/dicionario-de-dados.md)).
- [ ] Mapeamento de base legal por finalidade.
- [ ] RIPD, se a avaliação de risco recomendar (provável, dado o público).
- [ ] Indicação do encarregado e canal do titular.
- [ ] Contratos com operadores (hospedagem, e-mail, push, WhatsApp).
- [ ] Política de retenção, descarte e anonimização por tipo de dado.
- [ ] Procedimento de resposta a incidentes e comunicação à ANPD.
- [ ] Aviso de privacidade e termos versionados ([RF-04](../01-requisitos/funcionais/RF-04-consentimento-e-privacidade.md)).

## Regras fixas
- Minimização e privacidade desde a concepção.
- Proibida publicidade comportamental direcionada a crianças e uso de dados para finalidades alheias ao EBI.
- Consentimentos facultativos (imagem, notificações não essenciais) separados do tratamento necessário à operação.
- Nenhum dado de participante na biblioteca de atividades ([RN-12](../02-regras-de-negocio/regras-de-negocio.md#rn-12)).
- Supressão de contagens pequenas em relatórios ([métricas](../02-regras-de-negocio/metricas-e-indicadores.md)).
