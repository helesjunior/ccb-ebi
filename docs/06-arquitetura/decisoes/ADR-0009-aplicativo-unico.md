# ADR-0009 — Um único aplicativo para todos os perfis

**Status:** aceito · **Data:** 08/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-07 (em parte)

## Contexto
A especificação comparava um app com módulos por papel e dois apps separados (equipe e responsáveis).

## Decisão
**Um único aplicativo** com todas as visões: Responsável, Coordenadora, Auxiliar, Ministério Local, Setorial, Regional e Nacional. O conteúdo exibido depende dos papéis e do contexto escolhido pelo usuário.

## Consequências
- Navegação por perfil: depois do login, o usuário escolhe o contexto (RN-11). Quem só é responsável vê direto a área de dependentes.
- MFA continua obrigatório para perfis que não sejam Responsável, dentro do mesmo app.
- A segurança depende inteiramente da autorização no servidor; esconder telas no app não protege nada.
- **Continua em aberto:** quem é titular da conta de desenvolvedor nas lojas (parte restante de D-07).
