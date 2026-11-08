# RF-02 — Cadastro único de participantes

**Status:** proposto · **Épico:** [E03](../../07-produto/backlog-epicos.md) · **Fase:** 1

## Requisitos
1. Gerar **matrícula nacional imutável e não sequencialmente previsível**.
2. Dados mínimos: nome completo, data de nascimento, nome social quando aplicável, vínculo com responsáveis, situação cadastral, igreja/EBI de referência (opcional), datas de criação e atualização.
3. Dados adicionais (fotografia, necessidades de acessibilidade, contatos de emergência, saúde) só se indispensáveis, com acesso restrito e base legal específica. Não coletar por padrão.
4. Pesquisa por matrícula e nome, limitada à finalidade e ao escopo do operador, com critérios adicionais controlados para evitar homônimos.
5. Detectar possíveis duplicidades por combinação de atributos e revisão humana, sem fusão automática irreversível.
6. O **responsável cria** o cadastro; a **coordenadora mescla e corrige**, com auditoria e possibilidade de desfazer ([ADR-0007](../../06-arquitetura/decisoes/ADR-0007-cadastro-e-correcao-de-participantes.md)).
7. **Cadastro assistido:** a coordenadora pode cadastrar presencialmente famílias sem e-mail ou com pouca familiaridade digital ([ADR-0015](../../06-arquitetura/decisoes/ADR-0015-cadastro-assistido.md), [FL-07](../fluxos/FL-07-cadastro-assistido.md)).
8. Registrar histórico de vínculos, atualizações, correções e inativação.
9. Aplicar a [regra de elegibilidade etária](../../02-regras-de-negocio/elegibilidade-etaria.md) e manter o cadastro de exceção inclusiva descrito nela.
10. Participante com 12 anos ou mais, amparado por exceção, mantém a mesma matrícula e histórico.

## Relacionados
- Regras: [RN-01](../../02-regras-de-negocio/regras-de-negocio.md#rn-01), [RN-12](../../02-regras-de-negocio/regras-de-negocio.md#rn-12), [RN-13 a RN-16](../../02-regras-de-negocio/elegibilidade-etaria.md)
- Fluxos: [FL-01](../fluxos/FL-01-cadastro-responsavel-e-participante.md), [FL-06](../fluxos/FL-06-excecao-inclusiva.md), [FL-07](../fluxos/FL-07-cadastro-assistido.md)
- Critérios de aceite: [CA-01](../criterios-de-aceite.md#ca-01), [CA-05](../criterios-de-aceite.md#ca-05), [CA-06](../criterios-de-aceite.md#ca-06), [CA-17](../criterios-de-aceite.md#ca-17), [CA-19](../criterios-de-aceite.md#ca-19), [CA-21](../criterios-de-aceite.md#ca-21)
- Dados: `participante`, `excecao_etaria`, `dado_sensivel_participante`
- API: `POST /participants`, `/participants/{id}/age-exceptions`

## Pendências
- Escopo da coordenadora para mesclar e corrigir: proposta de limitar a participantes com presença ou referência nos EBIs onde ela atua ([ADR-0007](../../06-arquitetura/decisoes/ADR-0007-cadastro-e-correcao-de-participantes.md)).
- Evidência do consentimento no cadastro assistido e concordância do participante adulto (jurídico, [D-04](../../06-arquitetura/decisoes/README.md)).
