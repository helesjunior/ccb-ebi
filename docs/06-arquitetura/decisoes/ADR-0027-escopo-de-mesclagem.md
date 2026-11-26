# ADR-0027 — Escopo da coordenadora para mesclar e corrigir cadastros

**Status:** aceito · **Data:** 09/11/2026 · **Aprovado por:** Heles Júnior · **Resolve:** D-22

## Contexto
O [ADR-0007](ADR-0007-cadastro-e-correcao-de-participantes.md) deu à coordenadora o poder de mesclar e corrigir cadastros, sem dizer até onde. Sem limite, qualquer coordenadora alteraria cadastros do país inteiro.

## Decisão
- A coordenadora só mescla ou corrige participantes que tenham **presença ou igreja de referência em um EBI onde ela atua**.
- Casos fora desse escopo (por exemplo, duplicidade entre participantes de EBIs diferentes) vão para a **coordenadora chefe**.
- Toda mesclagem e correção continua auditada e reversível.

## Regra implementada (2.21)
- **Ligado:** o escopo do ator cobre a igreja de referência do participante. Só quando ele **não tem igreja** conta a presença `valida` em EBI do escopo — presença não liga cadastro de outra igreja, nem presença estornada. `super_admin` está sempre ligado.
- **Mesclar:** a coordenadora precisa dos dois cadastros ligados; a coordenadora chefe, de ao menos um. **Desfazer** confere o principal e o duplicado do snapshot com a mesma regra. Se o principal herdou outra igreja na mesclagem, só a chefe dessa igreja (ou o `super_admin`) desfaz.
- **Corrigir** (`PATCH /participants/{id}`): mesma regra, `motivo` de 3 a 255 caracteres, nova igreja dentro do escopo, cadastro `mesclado` responde 409. Corrigir o principal com mesclagem pendente atualiza o snapshot, e o desfazer preserva a correção.
- **Risco aceito:** a chefe alcança como duplicado qualquer cadastro do país.

## Consequências
- A [matriz de autorização](../../05-seguranca-e-privacidade/matriz-de-autorizacao.md) aplica o vínculo com o EBI nessas operações.
- Novo critério de aceite [CA-27](../../01-requisitos/criterios-de-aceite.md#ca-27).
