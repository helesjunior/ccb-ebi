# Regras de negócio

Regras agrupadas por domínio. Os IDs são os mesmos da versão anterior e não são renumerados; por isso a ordem não é sequencial. As regras de idade (RN-13 a RN-16) moram em [elegibilidade etária](elegibilidade-etaria.md).

## Cadastro e vínculos

| ID | Regra | Status |
|---|---|---|
| <a id="rn-01"></a>RN-01 | A matrícula do participante é única nacionalmente e não muda quando ele frequenta outra igreja. | proposto |
| <a id="rn-05"></a>RN-05 | O responsável só acessa o dependente depois de vínculo legitimado e ativo. | proposto |
| <a id="rn-12"></a>RN-12 | Dados pessoais de participantes não entram em arquivos compartilhados nacionalmente. | proposto |

## Elegibilidade etária

| ID | Regra | Status |
|---|---|---|
| RN-13 | Faixa etária regular. | ver [elegibilidade etária](elegibilidade-etaria.md#rn-13) |
| RN-14 | Exceção inclusiva sem limite máximo de idade. | ver [elegibilidade etária](elegibilidade-etaria.md#rn-14) |
| RN-15 | Fim da exceção bloqueia novas presenças. | ver [elegibilidade etária](elegibilidade-etaria.md#rn-15) |
| RN-16 | Cálculo da idade (novo na 2.0). | ver [elegibilidade etária](elegibilidade-etaria.md#rn-16) |

## Sessões e presença

| ID | Regra | Status |
|---|---|---|
| <a id="rn-02"></a>RN-02 | A presença exige sessão existente e autorização do operador para o EBI correspondente. | proposto |
| <a id="rn-03"></a>RN-03 | Um registro ativo de presença por participante e sessão. Estorno não apaga o registro; ele passa a `estornada` e permite novo registro ativo. | proposto |
| <a id="rn-04"></a>RN-04 | Igreja sugerida por GPS precisa ser confirmada pelo operador, junto com o EBI e a sessão; a seleção manual está sempre disponível. Proximidade não é prova de presença. | proposto |
| <a id="rn-09"></a>RN-09 | Transferências organizacionais não mudam a igreja, o setor nem a regional históricos da sessão. | proposto |

## Atividades

| ID | Regra | Status |
|---|---|---|
| <a id="rn-06"></a>RN-06 | Material reutilizado precisa estar associado a uma sessão e a uma versão identificáveis. | proposto |
| <a id="rn-07"></a>RN-07 | Download e visualização não aumentam o contador de utilização. | proposto |
| <a id="rn-08"></a>RN-08 | Média de estrelas e contagem de utilizações são métricas independentes. | proposto |

## Acesso e auditoria

| ID | Regra | Status |
|---|---|---|
| <a id="rn-10"></a>RN-10 | Alterações em presença, consentimento, vínculos, exceções e feedbacks geram auditoria. | proposto |
| <a id="rn-11"></a>RN-11 | Usuário com vários vínculos escolhe o contexto de atuação; o acesso é sempre validado no backend. | proposto |

## Mudanças em relação à versão anterior
- RN-03: termo "criança" trocado por "participante" e explicitado o efeito do estorno.
- RN-09: incluídos setor e regional, além da igreja.
- RN-10: incluídas as exceções inclusivas.
- RN-16: nova.
