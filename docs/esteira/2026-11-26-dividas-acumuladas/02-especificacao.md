# Especificação técnica

## Solução

A demanda tem cinco itens independentes e três correções:

- o limite de motivo;
- o território na decisão de exceção (seção 3a);
- a validação da igreja no cadastro (seção 3).

Cada um pode ir num commit separado. A ordem está em `03-plano-implementacao.md`. As tarefas T1 a T4 (limite de motivo e desistir da troca) não dependem do resto e podem ser entregues antes.

Regras de arquitetura que valem para tudo (`backend/tests/Arch/ModularBoundariesTest.php`):

- `app/Http` não importa `App\Domain\*\Models\*`.
- Um módulo só importa de outro o que está em `Contracts/`.
- Helper que precisa de `ContextoAtuacao` fica em `app/Support`.

### 1. Limite de motivo (CA12)

`evento_auditoria.motivo` é `string` (255) em `backend/database/migrations/2026_11_08_000001_create_evento_auditoria_table.php:21`. Todo motivo que vai para a auditoria passa a ter limite compatível com isso:

| Onde | Hoje | Passa a |
|---|---|---|
| `backend/app/Http/Requests/Agenda/ContingenciaPresencaRequest.php:19` | `max:500` | `max:255` |
| `backend/app/Http/Requests/Agenda/EstornarPresencaRequest.php:17` | `max:500` | `max:255` |
| `backend/app/Http/Requests/Agenda/CancelarSessaoRequest.php:17` | `max:500` | `max:255` |
| `backend/app/Http/Requests/Agenda/CorrigirSessaoRequest.php:24` | `max:500` | `max:255` |
| `backend/app/Domain/Sessoes/Services/GerenciadorSessao.php:59` (cancelar) e `:85` (corrigir) | `max:500` | `max:255` |
| `backend/app/Http/Requests/Agenda/AgendaExcecaoRequest.php:23` | sem limite | `'min:3', 'max:247'` |
| `backend/app/Domain/Agenda/Services/GerenciadorAgenda.php:42` | sem limite | `'min:3', 'max:247'` |
| `backend/app/Http/Requests/Organizacao/TransferenciaRequest.php:22` (`reason`, gravado como `motivo` em `ServicoTransferencia.php:100`) | `max:500` | `max:255` |
| `docs/04-api/openapi.yaml` linhas 3932, 3942, 3993 e 3998 | `maxLength: 500` | `maxLength: 255` |
| `docs/04-api/openapi.yaml:673` (`reason` de `/churches/{id}/transfers`) | sem limite | `maxLength: 255` |
| `docs/04-api/openapi.yaml:3576` (`AgendaExcecaoInput`) | `maxLength: 500` | `maxLength: 247` |

O 247 vem de `CanceladorSessoesPrevistas.php:41`, que grava `"agenda: {$motivo}"` na auditoria: são 8 caracteres de prefixo, e 247 + 8 = 255. O `min:3` da agenda já está no OpenAPI. Hoje o servidor aceita "a", e isso passa a dar 422. É o contrato publicado, então a mudança é intencional.

Ao lado de cada regra, ponha o comentário `// evento_auditoria.motivo e varchar(255).`, como já existe em `CorrigirParticipanteRequest.php:24`.

No front:

- `frontend/src/pages/Presenca.tsx`, linhas 66, 67, 281, 282, 406 e 407: `500` → `255`, inclusive no texto "(3 a 255 caracteres)";
- `frontend/src/pages/Sessoes.tsx`, linhas 57 e 314: `500` → `255`;
- `frontend/src/components/agenda/PainelAgenda.tsx`:
  - linha 259: `minLength={3} maxLength={247}` e o rótulo "Motivo (obrigatório, 3 a 247 caracteres)";
  - linha 260: no `disabled` de "Criar exceção", troque `!motivo.trim()` por `motivo.trim().length < 3`;
  - linha 147, no início de `criarExcecao`: troque `!motivo.trim()` por `motivo.trim().length < 3`.
- A transferência de igreja não tem campo de motivo no portal (nenhuma tela chama `/churches/{id}/transfers`), então o front não muda.

Fica de fora, porque não grava texto livre em `evento_auditoria.motivo` (conferido na revisão):

- a decisão de exceção e de vínculo (`maxLength: 500` em `openapi.yaml`, linhas 3484 e 3514), que grava `motivo => null` na auditoria e guarda o motivo em colunas `text`;
- o pedido de troca, cujo motivo fica em `solicitacao_troca_escala.motivo varchar(500)`;
- o cancelamento de troca e o encerramento de vaga, que usam constantes.

### 2. Desistir da troca (CA10, CA11)

**Backend.** `ConsultaEscala::minhas()` (`backend/app/Domain/Escalas/Services/ConsultaEscala.php:204-273`) monta o mapa `membro_id => troca_id` numa única consulta:

```php
$trocas = SolicitacaoTrocaEscala::query()
    ->whereIn('escala_membro_id', array_map(fn (EscalaMembro $v): string => (string) $v->id, $noPeriodo))
    ->where('solicitante_id', $usuarioId)
    ->where('status', SolicitacaoTrocaEscala::STATUS_PENDENTE)
    ->pluck('id', 'escala_membro_id')->all();
```

Cada linha ganha `'troca_id' => isset($trocas[(string) $vaga->id]) ? (string) $trocas[(string) $vaga->id] : null`. O model já está importado na linha 12. Atualize o `@return` do contrato em `backend/app/Domain/Escalas/Contracts/ConsultarEscala.php:60` com `troca_id: ?string`.

**Front.** Em `frontend/src/pages/MinhasEscalas.tsx`, no bloco das linhas 280-282, quando `linha.status === 'troca_solicitada' && linha.troca_id`:

```tsx
<ConfirmarAcao
  titulo="Desistir da troca?"
  descricao="Você continua escalada nesta sessão."
  rotuloConfirmar="Desistir da troca"
  onConfirmar={() => desistir(linha.troca_id!)}
>
  <Button type="button" variant="outline" size="sm" disabled={enviando}>Desistir da troca</Button>
</ConfirmarAcao>
```

`desistir(trocaId)` chama `cancelarTroca` (`frontend/src/api/escalas.ts:309`) e segue o padrão de `retirarMinhaOferta`:

- no erro: `setErro(lerErro(error, 'Não foi possível desistir da troca.').detail)` e `await carregar()`, porque a troca pode ter sido decidida por outra pessoa;
- no sucesso: `toast.success('Pedido de troca cancelado.')` e `await carregar()`.

O backend já devolve 404 para quem não é o solicitante (`GerenciadorTrocaEscala.php:94-96`) e `409 troca_ja_decidida` (`:98-100`). Também já devolve o membro ao status anterior (`devolverMembro`, `:296`).

### 3. Escopo nos eventos de auditoria (CA8, CA9)

**Helper novo:** `backend/app/Support/Participantes/EscopoAuditoria.php`.

```php
final class EscopoAuditoria
{
    public function __construct(
        private readonly ConsultarHierarquia $hierarquia,
        private readonly ConsultarOrganizacao $organizacao,
    ) {}

    /** "tipo:id" do contexto de atuacao; null sem contexto. */
    public static function doContexto(?ContextoAtuacao $contexto): ?string
    {
        return $contexto === null ? null : "{$contexto->tipoEscopo}:{$contexto->escopoId}";
    }

    /**
     * Territorio do participante: ebi:<id> se a igreja tem EBI, igreja:<id> se
     * nao tem, null sem igreja ou com igreja inexistente (cadastro legado).
     */
    public function daIgreja(?string $igrejaId): ?string
    {
        if ($igrejaId === null || $igrejaId === '') {
            return null;
        }
        $ebiId = $this->hierarquia->ebiDaigreja($igrejaId);
        if ($ebiId !== null) {
            return "ebi:{$ebiId}";
        }

        return $this->organizacao->nomes('igreja', [$igrejaId]) === [] ? null : "igreja:{$igrejaId}";
    }
}
```

`ConsultarHierarquia` e `ConsultarOrganizacao` ficam em `App\Domain\Organizacao\Contracts`, então as dependências são permitidas. `ConsultarOrganizacao::nomes` deixa de fora os ids que não existem (linhas 35-42). A regra de `daIgreja` (decisão do humano) repete `AutorizacaoParticipante::cobreIgreja` (linhas 93-106) e o formato `ebi:<id>` que os eventos de presença já usam (`ContingenciaTest.php:34`).

**Validação da igreja no cadastro.** Em `backend/app/Http/Requests/Participantes/CadastrarParticipanteRequest.php:23` e em `CadastrarAssistidoRequest.php:23`, a regra passa de `['nullable', 'string', 'max:255']` para `['nullable', 'string', 'exists:igrejas,id']`, igual a `CorrigirParticipanteRequest.php:23`. Isso fecha dois problemas:

- o responsável deixa de poder escolher em que território o evento aparece, informando uma igreja que não existe;
- um id longo deixa de estourar `evento_auditoria.escopo` (`varchar(255)`).

A checagem de existência dentro de `daIgreja` cobre os cadastros antigos, que já estão gravados com um id que não existe. É o que alimenta `vinculo.solicitado` e `excecao.solicitada`.

Teste que quebra e precisa mudar: `backend/tests/Feature/Participantes/CadastroParticipanteTest.php:30` usa `(string) Str::ulid()` como igreja. Ele passa a usar `igrejaComEbi()['igreja']->id`.

**Serviços.** Cada método ganha o último parâmetro `?string $escopo = null` e grava esse valor em vez de `'escopo' => null`. O contrato muda junto. Nenhum serviço calcula o escopo: o controller calcula e repassa, como `corrigir` já faz.

| Contrato (`backend/app/Domain/Participantes/Contracts/`) | Serviço (`backend/app/Domain/Participantes/Services/`) | Métodos |
|---|---|---|
| `GerirParticipante` | `GerenciadorParticipante` (`cadastrar` 33-62; `cadastrarAssistido` 136+, gravação perto da linha 199) | `cadastrar`, `cadastrarAssistido` |
| `GerirMesclagem` | `GerenciadorMesclagem` (`registrar` privado, linha 140) | `mesclar`, `desfazer` |
| `GerirVinculoResponsavel` | `GerenciadorVinculoResponsavel` (`registrar` privado, linha 149) | `solicitar`, `decidir` |
| `GerirExcecaoEtaria` | `GerenciadorExcecao` (`registrar` privado, linha 75) | `solicitar`, `decidir` |

Os `registrar` privados ganham o parâmetro `?string $escopo` e o repassam.

**Escopo por evento** (é isto que o CA8 confere):

| Evento | Controller (`backend/app/Http/Controllers/Api/V1/`) | Escopo gravado |
|---|---|---|
| `participante.cadastrado`, origem `responsavel` (`POST /participants`) | `ParticipantController::store` | papel do contexto `responsavel`: `daIgreja(igreja_referencia_id do corpo)`. Outro papel: `doContexto`. A rota exige contexto (`can:participante.cadastrar`, `ParticipantePolicy::decidir` nega sem contexto). |
| `participante.cadastrado`, origem `assistido` | `ParticipantController::storeAssisted` | `doContexto` |
| `participante.lido` (`GET /participants`, `GET /participants/{id}`) | `ParticipantController::auditarLeitura` (182-194) | `doContexto` |
| `participante.lido` (`GET /ebis/{id}/age-alerts`, novo) | `AgeExceptionController::alertas` | `"ebi:{id}"` |
| `participante.mesclado`, `participante.mesclagem_desfeita` | `ParticipantMergeController::store`/`undo` | `doContexto` |
| `vinculo.solicitado` | `GuardianController::store` | `daIgreja($participante->igreja_referencia_id)` |
| `vinculo.decidido` | `GuardianRequestController::update` | `doContexto` |
| `vinculo.listado` | `GuardianController::pedidos` (linha 86) | `doContexto` |
| `excecao.solicitada` | `AgeExceptionController::store` | `daIgreja($participante->igreja_referencia_id)` |
| `excecao.decidida` | `AgeExceptionController::update` | `doContexto`, depois da checagem de território da seção 3a |

Na solicitação de vínculo e de exceção, o escopo vem do participante, e não de quem pede. O pedido costuma vir do responsável (escopo `central`), e o que interessa à auditoria territorial é onde a criança está. Sem igreja, esses dois eventos ficam com `null`.

Na decisão, vale o contexto de quem decide. O território dele já foi conferido: o vínculo em `GuardianRequestController::update` (linha 41) e a exceção pela seção 3a, que é nova.

Ajuste preso a isso: `AgeExceptionController::contexto()` (linha 66) está tipado para `SolicitarExcecaoRequest`. Ele passa a receber `Illuminate\Http\Request`, para `update` também poder usá-lo.

### 3a. Território na decisão de exceção (CA13)

Hoje `PATCH /age-exceptions/{id}` só passa por `can:excecao.decidir` (`backend/routes/api.php:214-215`), que confere o papel sem olhar o recurso. A correção segue o padrão de `GuardianRequestController::update` (linhas 31-43).

1. Novo método no contrato `backend/app/Domain/Participantes/Contracts/GerirExcecaoEtaria.php`, implementado em `GerenciadorExcecao`:

   ```php
   public function encontrar(string $excecaoId): ?ExcecaoEtaria; // ExcecaoEtaria::query()->find($excecaoId)
   ```

2. Em `AgeExceptionController::update`, antes de chamar `decidir`:

   ```php
   $excecao = $this->excecoes->encontrar($id);
   $participante = $excecao !== null
       ? $this->participantes->encontrar((string) $excecao->participante_id)
       : null;

   // Fora do territorio a excecao se comporta como inexistente (404), como o vinculo.
   if ($excecao === null || $participante === null
       || ! $this->autorizacao->ligado($this->contexto($request), $participante, Operacoes::EXCECAO_DECIDIR)) {
       throw new ProblemException('not_found', 'Recurso não encontrado', 404, 'Exceção não encontrada.');
   }
   ```

   Importe `App\Domain\Identidade\Support\Operacoes` e `Illuminate\Http\Request`.

A checagem usa `ligado`, e não `cobreTerritorio`, por dois motivos:

- `ligado` deixa o `super_admin` passar (linha 120). `cobreTerritorio` devolve falso para participante sem igreja, até para o `super_admin`;
- para participante sem igreja, `ligado` aceita uma presença `valida` num EBI do escopo (CA-27). Sem isso, a exceção de uma criança sem igreja não teria quem a decidisse.

Com igreja, as duas checagens são iguais (`cobreIgreja`).

Fora do território, a resposta é `404`. É o mesmo status do vínculo, e ele não revela que a exceção existe. Quem não tem o papel continua recebendo `403` do middleware.

Testes que quebram e precisam mudar: `backend/tests/Feature/Participantes/ExcecaoEtariaTest.php`, linhas 35-55. Eles criam a coordenadora num EBI aleatório e a exceção num participante sem igreja. Passam a usar `igrejaComEbi()`, com a coordenadora em `ebi:<ebi>` e a exceção em `ExcecaoEtaria::factory()->create(['participante_id' => participanteNaIgreja($igreja)->id, ...])`. O helper `participanteNaIgreja(Igreja $igreja, array $atributos = [])` está em `backend/tests/Pest.php:121`.

Os eventos antigos continuam com `escopo = null`. Não há migração de dados (ver "Decisões").

### 4. Aviso dos 12 anos (CA3, CA4, CA5)

**Cálculo.** É feito na leitura, sem tabela e sem rotina agendada. Fica em `backend/app/Domain/Participantes/Services/AvaliadorElegibilidade.php`, que já tem `idadeEm` (RN-16) e `excecaoVigente`. Dois métodos públicos novos, também declarados no contrato `backend/app/Domain/Participantes/Contracts/AvaliarElegibilidade.php`:

```php
/**
 * Data local (Y-m-d) do 12o aniversario quando ela cai em [hoje, hoje + $dias]
 * no fuso dado, o participante esta ativo e nao ha excecao aprovada vigente
 * nessa data. Senao, null.
 */
public function aviso12Anos(Participante $participante, DateTimeInterface $agora, string $fuso, int $dias = 30): ?string;

/**
 * Participantes ativos com igreja_referencia_id = $igrejaId e aviso nao nulo,
 * ordenados por data e nome.
 *
 * @return array<int, array{participante: Participante, faz_12_em: string}>
 */
public function aviso12AnosDaIgreja(string $igrejaId, DateTimeInterface $agora, string $fuso, int $dias = 30): array;
```

Como implementar `aviso12Anos`:

1. Se `status !== Participante::STATUS_ATIVO`, devolva `null`.
2. `$hoje = DateTimeImmutable::createFromInterface($agora)->setTimezone(new DateTimeZone($fuso))`, só a data.
3. Aniversário: ano de nascimento + 12, mesmo mês e dia. Se for 29/02 e o ano não for bissexto, use 01/03, com o `bissexto()` que já existe (RN-16).
4. Fora de `[$hoje, $hoje + $dias dias]` (comparando `Y-m-d`), devolva `null`.
5. `$excecao = $participante->excecaoVigente()`. Se não for nula e `$this->excecaoVigente($excecao, $aniversario)` der verdadeiro, devolva `null`.
6. Devolva o aniversário em `Y-m-d`.

`aviso12AnosDaIgreja` faz um pré-filtro no SQL e depois aplica `aviso12Anos` em cada linha:

```php
Participante::query()
    ->where('igreja_referencia_id', $igrejaId)
    ->where('status', Participante::STATUS_ATIVO)
    ->whereBetween('data_nascimento', [
        $hoje->modify('-12 years')->modify('-1 day')->format('Y-m-d'),
        $hoje->modify("+{$dias} days")->modify('-12 years')->modify('+1 day')->format('Y-m-d'),
    ])
```

A margem de um dia de cada lado cobre o 29/02. O filtro exato fica no PHP.

**Responsável: `GET /me/participants`.** Em `GuardianController::meusDependentes` (linhas 57-64), cada item passa a ser `ParticipanteResource` + `faz_12_em`. O fuso vem de `ConsultarOrganizacao::fusosDasIgrejas($igrejaIds)` (`backend/app/Domain/Organizacao/Contracts/ConsultarOrganizacao.php:59`), com uma chamada por requisição. Sem igreja, ou sem fuso, usa `'America/Sao_Paulo'`, o mesmo padrão de `IgrejaFactory` e `ImportadorBrasilia`. A rota não pede finalidade nem grava auditoria: o acesso é por propriedade (CA-04), e o responsável já é isento.

**Coordenadora: `GET /ebis/{id}/age-alerts` (novo).** Em `AgeExceptionController::alertas(Request $request, string $id)`:

1. `Gate::authorize(Operacoes::EXCECAO_DECIDIR, ['ebi', $id]);`
   - `excecao.decidir` vale para `coordenadora`, `coordenadora_chefe` e `super_admin` (`AutorizadorOperacao.php:28`, `:54`);
   - o `super_admin` passa por cima do escopo (`:173`);
   - os outros papéis recebem 403.
2. `$finalidade = FinalidadeLeitura::exigir($request);`
3. `$ebi = $this->ebis->dados($id) ?? throw new ProblemException('not_found', 'Recurso não encontrado', 404);`. `ConsultarEbi` fica em `App\Domain\Organizacao\Contracts`.
4. `$linhas = $this->elegibilidade->aviso12AnosDaIgreja($ebi->igrejaId, now(), $ebi->timezone ?: 'America/Sao_Paulo');`. O padrão é o mesmo de `meusDependentes`, para não chamar `new DateTimeZone('')`.
5. Auditoria `participante.lido` com:
   - `escopo => "ebi:{$id}"`;
   - `motivo => $finalidade`;
   - `metadados_minimizados => ['participante_ids' => [...], 'origem_leitura' => 'aviso_12_anos']`.
6. Resposta: `{"data":[{"participante_id","nome","matricula","faz_12_em"}]}`.

Rota em `backend/routes/api.php`, logo depois da linha 215, sem middleware `can:`, porque o Gate fica no controller com o recurso:

```php
Route::get('ebis/{id}/age-alerts', [AgeExceptionController::class, 'alertas'])->name('ebis.age-alerts');
```

**Front, responsável.** Em `frontend/src/pages/Dependentes.tsx`, acima da tabela, um `<Alert role="status" data-testid="aviso-12-anos">` para cada dependente com `faz_12_em`. O texto está no CA3; a data é formatada com `formatarData`.

**Front, coordenadora.** `frontend/src/pages/Inicio.tsx` ganha um card "Fazem 12 anos nos próximos 30 dias" (`data-testid="aniversarios-12"`). Ele só aparece quando:

```ts
escopo?.tipo_escopo === 'ebi' && ['coordenadora', 'coordenadora_chefe'].includes(escopo.papel ?? '')
```

O card chama `api.GET('/ebis/{id}/age-alerts', { params: { path: { id: escopo.escopo_id }, query: { finalidade: 'aviso_12_anos' } }, headers: cabecalhos() })`. Ele mostra uma lista com nome, matrícula e data, e um botão "Exceção inclusiva" que chama `onSelecionarTela('excecao')`. A lista vazia e o erro usam os textos do CA4 e do CA5. Inclua `{ valor: 'aviso_12_anos', rotulo: 'Acompanhar quem faz 12 anos' }` em `SUGESTOES_FINALIDADE` (`frontend/src/lib/formatos.ts:189`).

### 5. Contingência em lote (CA6, CA7)

**Serviço.** Novo método no contrato `backend/app/Domain/Sessoes/Contracts/RegistrarPresenca.php`, implementado em `backend/app/Domain/Sessoes/Services/RegistradorPresenca.php`:

```php
/**
 * @param  array<int, string>  $participanteIds  distintos, 1..100
 * @return array<int, array{participante_id: string, resultado: 'criada'|'ja_existia'|'erro', erro?: string, motivo_bloqueio?: ?string, presenca?: PresencaRegistrada}>
 */
public function registrarContingenciaLote(string $sessaoId, array $participanteIds, string $motivo, string $usuarioId): array;
```

1. `$sessao = $this->sessoes->detalhe($sessaoId) ?? 404`. Se `status !== Sessao::STATUS_ENCERRADA`, lança `409 sessao_em_estado_invalido`. Esse erro vale para o lote todo, e nada é gravado.
2. Para cada id, na ordem recebida:
   - se `$this->participantes->encontrar($id) === null` (`ConsultarParticipante`), a linha é `erro: not_found`. Isso é obrigatório porque `avaliarPorId` lança `RuntimeException` quando o participante não existe. `ConsultaParticipante::encontrar` (linhas 39-46) também exclui o participante `mesclado`: ele volta como `not_found`, e é o comportamento desejado, porque a presença tem de ir para o cadastro principal. Na tela, ele aparece em "Não lançadas", como os outros;
   - senão, `try { $r = $this->registrarContingencia($sessaoId, $id, $motivo, $usuarioId); }`. O resultado é `criada` quando `$r->nova` e `ja_existia` quando não, sempre com `presenca => $r->presenca`;
   - `catch (ProblemException $e)`: a linha é `erro: $e->problemCode`, mais `motivo_bloqueio => $e->extra['motivo_bloqueio'] ?? null`.
3. Cada linha roda na própria transação, que `registrarContingencia` já abre, e grava o próprio `presenca.contingencia`. O replay (`ja_existia`) não grava evento, como hoje.

Quando a sessão muda de estado no meio do lote, cada linha seguinte recebe `erro: sessao_em_estado_invalido`, porque `registrarContingencia` confere o estado de novo. Isso é aceitável: a resposta diz o que foi gravado.

**HTTP.**

- Request novo: `backend/app/Http/Requests/Agenda/ContingenciaLotePresencaRequest.php`.

  ```php
  return [
      'participante_ids' => ['required', 'array', 'min:1', 'max:100'],
      'participante_ids.*' => ['required', 'ulid', 'distinct'],
      // evento_auditoria.motivo e varchar(255).
      'motivo' => ['required', 'string', 'min:3', 'max:255'],
  ];
  ```

  A existência é conferida por linha, sem `exists:`, para um id inválido não derrubar o lote.
- Em `PresencaController`, ao lado de `contingencia` (linhas 88-98):

  ```php
  public function contingenciaLote(ContingenciaLotePresencaRequest $request, string $id): JsonResponse
  {
      $sessao = $this->sessao($id);
      Gate::authorize(Operacoes::SESSAO_GERENCIAR, ['ebi', $sessao->ebiId]);
      $dados = $request->validated();
      $linhas = $this->registrador->registrarContingenciaLote($id, $dados['participante_ids'], $dados['motivo'], (string) $request->user()?->getAuthIdentifier());
      // serializa `presenca` com PresencaResource, como em `contingencia`
      return response()->json(['data' => $linhas, 'resumo' => [...contagem por resultado]]);
  }
  ```

- Rota em `backend/routes/api.php`, depois da linha 316:

  ```php
  Route::post('sessions/{id}/contingency-attendance/batch', [PresencaController::class, 'contingenciaLote'])
      ->middleware(IdempotenciaMiddleware::class)->name('sessions.attendance.contingency.batch');
  ```

**Front.** Em `frontend/src/pages/Presenca.tsx`, o modo contingência passa a usar multisseleção. O modo normal (sessão `aberta`) continua igual.

- Estado novo: `const [marcados, setMarcados] = useState<Candidato[]>([])`. A seleção sobrevive a novas buscas. `limparBusca` não mexe em `marcados`; só a troca de modo e o envio limpam a lista.
- Nas linhas de candidato (312-323), com `emContingencia`, o botão alterna a pessoa em `marcados` (`aria-pressed` quando ela já está lá). Fora da contingência, o comportamento atual de `selecionado` continua.
- Logo abaixo do motivo (281-282), aparece a lista dos marcados (`data-testid="contingencia-marcados"`), cada um com um botão "Remover ‹nome›".
- A barra fixa (395-400), em contingência, aparece quando `marcados.length > 0` e tem:
  - o botão "Limpar seleção";
  - o botão "Lançar ‹n› presença(s)", desabilitado sem `motivoContingenciaValido`.
- `confirmar()`, em contingência, faz um único `api.POST('/sessions/{id}/contingency-attendance/batch', { ...options, body: { participante_ids: marcados.map(m => m.id!), motivo } })`. Na resposta:
  - toast `"‹criadas› lançadas, ‹ja_existiam› já existiam"`;
  - se houver erros, `setErro("Não lançadas: " + nomes.join(', '))`, com os nomes tirados de `marcados` pelo `participante_id`;
  - em seguida, limpa `marcados` e o motivo e chama `carregar()`.

  Erro HTTP (409, 422, 403) cai no `catch` que já existe.

O lote não precisa de um aviso de "elegibilidade mudou": o candidato inelegível já vem bloqueado na busca e não pode ser marcado. Se alguém ficar inelegível depois de marcado, a resposta da linha trata disso.

### 6. Tela de correção de cadastro (CA1, CA2)

- Componente novo: `frontend/src/components/CorrigirParticipante.tsx`. É um `Dialog` (`@/components/ui/dialog`), aberto por um botão "Corrigir" passado como gatilho.
  - Campos: nome, nome social, data de nascimento, igreja de referência e motivo (obrigatório, `minLength 3`, `maxLength 255`, rótulo "Motivo da correção (3 a 255 caracteres)"). Os quatro primeiros vêm preenchidos com o participante.
  - Os ids dos campos seguem o padrão de `FormularioParticipante.tsx`, com o prefixo `correcao-`.
  - No envio, o corpo leva só os campos que mudaram, mais o `motivo`. Nome social e igreja vazios vão como `null`. Nada mudou: o botão "Salvar correção" fica desabilitado.
  - Chama `api.PATCH('/participants/{id}', { params: { path: { id } }, headers: cabecalhos(), body })`.
  - Sucesso: `onCorrigido(data)`, `toast.success('Cadastro corrigido.')` e o diálogo fecha.
  - Erro: um `Alert` dentro do diálogo com `lerErro(error, 'Não foi possível corrigir o cadastro.').detail`, e o diálogo continua aberto.
- `frontend/src/pages/Participantes.tsx` recebe a prop `escopo: ContextoAtuacao | null`. Com `podeCorrigir = ['coordenadora', 'coordenadora_chefe', 'super_admin'].includes(escopo?.papel ?? '')`, cada linha (172-184) mostra `<CorrigirParticipante>` na coluna "Ações", ao lado do histórico. `onCorrigido` troca o item em `resultados`.
- Em `frontend/src/App.tsx:151`, a chamada passa a ser `<Participantes escopo={escopo} />`.

A decisão usa o papel do contexto de atuação, e não `usuario.papeis`, porque é o contexto que o servidor confere (`ParticipantController::update`, linhas 131-137).

### 7. Documentação

- `docs/04-api/openapi.yaml`:
  - nova rota `/sessions/{id}/contingency-attendance/batch`, depois da linha 2167;
  - nova rota `/ebis/{id}/age-alerts`, perto de `/ebis/{id}/team` (973);
  - schemas novos: `PresencaContingenciaLoteInput`, `ResultadoContingenciaLote`, `AlertaDozeAnos` e `Dependente`;
  - `MinhaEscala.troca_id`;
  - `/me/participants` passa a apontar para `Dependente`;
  - os limites de motivo da seção 1;
  - em `PATCH /age-exceptions/{id}`, a descrição do `404` passa a dizer "exceção inexistente ou fora do território".
- `docs/04-api/endpoints.md`: uma linha `no OpenAPI` para cada rota nova, depois das linhas 120 e 82.
- `docs/CHANGELOG.md`: versão **2.22 — ‹data do merge›**, com:
  - os itens desta demanda;
  - as quebras de contrato: motivo limitado a 255 caracteres, agenda com 3 a 247, `igreja_referencia_id` validado no cadastro e `404` fora do território na decisão de exceção;
  - a lista do que ficou fora (`01-historia.md`).

## Contratos

### `POST /sessions/{id}/contingency-attendance/batch`

Cabeçalhos: `Authorization`, `X-Acting-Scope` e `Idempotency-Key` (opcional).

```json
{ "participante_ids": ["01J...A", "01J...B", "01J...C"], "motivo": "Lista em papel, queda de internet" }
```

`200`:

```json
{
  "data": [
    { "participante_id": "01J...A", "resultado": "criada", "presenca": { "...": "Presenca" } },
    { "participante_id": "01J...B", "resultado": "ja_existia", "presenca": { "...": "Presenca" } },
    { "participante_id": "01J...C", "resultado": "erro", "erro": "inelegivel", "motivo_bloqueio": "excecao_ausente_ou_invalida" }
  ],
  "resumo": { "criadas": 1, "ja_existiam": 1, "erros": 1 }
}
```

Erros do lote inteiro:

- `403`: sem `sessao.gerenciar` no EBI da sessão;
- `404`: sessão inexistente;
- `409 sessao_em_estado_invalido`: sessão não `encerrada`;
- `422`: validação (lista vazia, mais de 100 itens, repetidos, id que não é ULID, motivo fora de 3 a 255).

Schema OpenAPI:

```yaml
PresencaContingenciaLoteInput:
  type: object
  required: [participante_ids, motivo]
  properties:
    participante_ids:
      type: array
      minItems: 1
      maxItems: 100
      uniqueItems: true
      items: { type: string }
    motivo: { type: string, minLength: 3, maxLength: 255 }
ResultadoContingenciaLote:
  type: object
  required: [participante_id, resultado]
  properties:
    participante_id: { type: string }
    resultado: { type: string, enum: [criada, ja_existia, erro] }
    erro: { type: string, description: 'inelegivel | not_found (inexistente ou mesclado) | sessao_em_estado_invalido' }
    motivo_bloqueio: { type: [string, 'null'] }
    presenca: { $ref: '#/components/schemas/Presenca' }
```

Resposta `200`: `{ data: ResultadoContingenciaLote[], resumo: { criadas: integer, ja_existiam: integer, erros: integer } }`.

### `GET /ebis/{id}/age-alerts?finalidade=aviso_12_anos`

Parâmetros: `XActingScope`, `EbiId` e `FinalidadeLeitura` (`openapi.yaml`, linhas 2937 e 3007).

`200`:

```json
{ "data": [ { "participante_id": "01J...", "nome": "Ana Souza", "matricula": "ABCD1234X", "faz_12_em": "2026-12-10" } ] }
```

Erros:

- `403`: sem `excecao.decidir` no EBI. Para quem não é `super_admin`, isso inclui o EBI que não existe;
- `404`: EBI inexistente, só para o `super_admin`;
- `422 finalidade_obrigatoria` e `422 finalidade_invalida`.

Schema `AlertaDozeAnos`: `participante_id`, `nome`, `matricula` (string) e `faz_12_em` (`date`), todos obrigatórios.

### `GET /me/participants`

Cada item passa de `Participante` para `Dependente`:

```yaml
Dependente:
  allOf:
    - $ref: '#/components/schemas/Participante'
    - type: object
      properties:
        faz_12_em: { type: [string, 'null'], format: date, description: 'RF-15: 12o aniversario nos proximos 30 dias sem excecao vigente.' }
```

### `GET /me/schedules`

`MinhaEscala` (`openapi.yaml:3835`) ganha `troca_id: { type: [string, 'null'], description: 'Troca pendente aberta pela propria pessoa; null nos demais casos.' }`.

### Auditoria

O formato do evento não muda. O campo `escopo` passa a ser preenchido conforme a tabela "Escopo por evento". O formato é sempre `"<tipo>:<id>"`, com o tipo em `central|regional|setor|igreja|ebi`, que é o que `ConsultaAuditoria::aplicarRecorteTerritorial` entende.

## Decisões

| Decisão | Alternativa descartada | Motivo |
|---|---|---|
| Aviso dos 12 anos calculado na leitura (decisão do humano, opção a) | Tabela de avisos e job diário com e-mail | Sem e-mail no escopo, a tabela só guardaria o que a leitura calcula em milissegundos. A tela é o único canal. |
| Coordenadora vê o aviso no Início, por rota própria `GET /ebis/{id}/age-alerts` | Filtro novo em `GET /participants` | A busca exige termo e passa pelo corte de supressão (ADR-0020). O aviso é uma lista curta, de propósito definido, com finalidade própria. |
| Gate da rota de aviso: `excecao.decidir` no EBI | `participante.ler` | Quem age sobre o aviso é quem decide a exceção. `participante.ler` incluiria auxiliar e `ministerio_local`, que não podem fazer nada com essa informação. |
| Responsável vê o aviso no campo `faz_12_em` de `/me/participants` | Rota separada | A tela já carrega essa lista, e o dado é do próprio dependente. |
| Lote: cada linha na própria transação, resultado por linha | Lote atômico (tudo ou nada) | Um inelegível na lista em papel não pode impedir o lançamento dos outros 40. `registrarContingencia` já trata o replay e a corrida de unicidade por linha. |
| Lote: `409` para o lote inteiro quando a sessão não está `encerrada` | Erro em cada linha | É uma condição do lote, conferida uma vez antes de qualquer gravação, e a resposta fica clara. |
| Lote: limite de 100 | Sem limite | Cada linha grava auditoria com `lockForUpdate` na cadeia (`RegistradorAuditoria.php:21-24`). 100 cobre uma lista em papel e mantém a requisição curta. |
| Rota individual de contingência continua | Remover | Contrato publicado, e já existem testes. O front passa a usar só o lote. |
| Escopo: parâmetro `?string $escopo = null` no fim de cada método do serviço | Parâmetro obrigatório; serviço calculando o escopo | O padrão vem de `corrigir`. Com o parâmetro opcional, os testes que chamam os serviços direto (`MesclagemTest`, `CorrecaoParticipanteTest`, `VinculoResponsavelTest` e outros) não mudam, e `decidir(..., ?string $motivo = null)` impede um obrigatório depois de um opcional. O controller tem o contexto; o serviço não. |
| Solicitação de vínculo e de exceção: escopo do participante | Escopo de quem pede | O responsável atua em `central`. Com o escopo dele, o evento só apareceria para o `super_admin`, que é o problema de hoje. |
| `daIgreja`: `ebi:` quando a igreja tem EBI, senão `igreja:`, senão `null` (**confirmado pelo humano**) | Sempre `igreja:` | É a mesma regra de `cobreIgreja`, e os eventos de presença já usam `ebi:`. Assim, a trilha do participante fica com um formato só. Para quem lê a auditoria hoje (`super_admin`, `adm_regional` e `adm_setor`), as duas formas dão o mesmo resultado. |
| Validar `igreja_referencia_id` no cadastro com `exists:igrejas,id` | Só fazer `daIgreja` devolver `null` para igreja inexistente | Sem a validação, o responsável escolhe em que território o evento aparece, e um id longo estoura `escopo varchar(255)`. A regra é a mesma da correção. A checagem dentro de `daIgreja` fica também, para cobrir os cadastros antigos. |
| Decisão de exceção fora do território responde `404` | `403` | É o mesmo padrão do vínculo (`GuardianRequestController`), e a resposta não revela que a exceção existe. |
| Território da exceção com `ligado` | `cobreTerritorio` | `ligado` deixa o `super_admin` passar e aceita a presença `valida` para participante sem igreja (CA-27). `cobreTerritorio` bloquearia essas exceções para todo mundo. |
| Eventos antigos continuam com `escopo = null` | Migração para preencher | Os eventos são encadeados por hash (`hash_encadeado`). Alterar o `escopo` quebraria a cadeia. |
| Motivos: limite de 255, incluindo a transferência de igreja, e de 3 a 247 na agenda (**confirmado pelo humano**) | Migrar `evento_auditoria.motivo` para `text` | A coluna faz parte do hash. 255 basta para um motivo, e a correção já usa esse limite (`CorrigirParticipanteRequest`). |
| Correção usa o papel do contexto de atuação | `usuario.papeis` | O servidor decide pelo contexto. Com `papeis`, o botão apareceria e daria 403 para quem tem o papel em outro contexto. |

## Segurança e autorização

- **Correção:** nada muda no servidor. A tela só esconde o botão, e o servidor continua aplicando `participante.corrigir`, `exigirLigados`, `cobreIgreja` e o motivo obrigatório.
- **`/ebis/{id}/age-alerts`:**
  - leitura identificável (nome, matrícula, data);
  - Gate territorial `excecao.decidir` com o recurso `['ebi', $id]`;
  - finalidade obrigatória, com auditoria `participante.lido` e escopo `ebi:<id>`;
  - quem não tem o papel recebe 403 antes de qualquer consulta, então a resposta não revela se o EBI existe.
- **`/me/participants`:** a autorização continua sendo por propriedade. O campo novo é derivado da data de nascimento, que já está na resposta. Não há dado novo exposto.
- **Lote:**
  - o Gate `sessao.gerenciar` no EBI é o mesmo da rota individual;
  - o limite de 100 e o `distinct` evitam abuso;
  - uma linha `not_found` não diz nada além do que o id enviado já dizia, porque não traz nome;
  - como na rota individual, as linhas `criada` e `ja_existia` devolvem a presença de qualquer participante do país cujo id seja informado. A busca da sessão já encontra cadastros do país inteiro (ADR-0027), então o lote não amplia a exposição. Restringir isso fica fora desta demanda.
- **Desistir:** o servidor já esconde a troca de terceiros (404). `troca_id` só vem para quem a pediu.
- **Escopo na auditoria:** amplia a visibilidade, de propósito, dentro do recorte de `ConsultaAuditoria`, que revalida com `ResolverEscopo::cobre`. Ninguém fora do território passa a ver eventos: o caso negativo está no CA9.
- **Decisão de exceção (CA13):** passa a conferir o território do participante com `AutorizacaoParticipante::ligado`, antes de qualquer gravação (seção 3a). Fora do território, a resposta é `404`, sem mudança de status e sem evento.
- **Cadastro:** `igreja_referencia_id` passa a ser validado com `exists:igrejas,id`. O escopo da auditoria deixa de ser escolhido pelo cliente.

## Riscos e mitigação

| Risco | Mitigação |
|---|---|
| Motivo de até 500 caracteres guardado em rascunho, ou enviado por outro cliente da API, agora recebe 422 | O portal limita o campo a 255. A mudança fica registrada no CHANGELOG. Os testes de 501/500 passam para 256/255. |
| Exceção de agenda com motivo de 1 a 2 caracteres passa a dar 422 | Esse já era o contrato do OpenAPI (`minLength: 3`). Fica registrado no CHANGELOG. |
| Lote grande segura a cadeia de auditoria (lock) por vários segundos | Limite de 100. Transação por linha, e o lock é liberado entre as linhas. |
| Dois lotes concorrentes com os mesmos participantes | A unicidade `presenca_valida_unica` mais o tratamento de `QueryException` em `registrarContingencia` (linhas 106-116). Coberto pelo teste de concorrência `CT-29`. |
| N+1 em `aviso12AnosDaIgreja` (`excecaoVigente()` por linha) | O pré-filtro SQL reduz a lista a quem faz 12 nos próximos ~30 dias, poucas pessoas por igreja. Marque com `// ponytail: N+1 em excecaoVigente; eager load se a lista crescer`. |
| Fuso errado na virada do dia muda quem entra na janela | O cálculo usa o fuso da igreja (EBI) ou `America/Sao_Paulo`. Há teste na virada (`CT-7`). |
| Escopo `"{tipo}:{id}"` de contexto com tipo desconhecido | `ConsultaAuditoria` ignora tipos desconhecidos (linhas 93-104). Os tipos vêm do `ContextoAtuacao` validado pelo middleware. |
| Cliente da API que cadastra com uma igreja que não existe passa a receber 422 | O portal envia o ID digitado. Antes, o id inválido gravava um participante sem território real. Fica registrado no CHANGELOG. |
| Coordenadora que hoje decide exceção de outro EBI passa a receber 404 | É a correção pedida (CA13). Fica registrado no CHANGELOG. |
| Front com o `schema.d.ts` desatualizado | Toda tarefa de front que usa contrato novo começa com `npm run gen:api`. O `npm run build` falha se faltar. |

## Respostas à revisão

### Rodada 1

Decisões do humano:

1. O território em `PATCH /age-exceptions/{id}` é **corrigido nesta demanda** (seção 3a, CA13, T7). A dívida saiu do item de CHANGELOG (agora T18).
2. O limite de motivo de 255 está **confirmado**, incluindo a transferência de igreja. Na exceção de agenda, fica de 3 a 247.
3. A regra de escopo pela igreja está **confirmada**: `ebi:<id>` quando a igreja tem EBI, senão `igreja:<id>`, senão `null`.

Bloqueantes, todos acatados:

| Item | O que mudou |
|---|---|
| Território na decisão de exceção | Seção 3a e CA13. `encontrar` no contrato `GerirExcecaoEtaria`, checagem com `ligado` e `404`. Casos CT-37 a CT-39. A frase "já foi conferido no território" foi corrigida. O CT-17 passa a montar a exceção no território da coordenadora. |
| `igreja_referencia_id` sem validação | `exists:igrejas,id` nos dois Requests de cadastro e `daIgreja` devolvendo `null` para igreja inexistente. Casos CT-40 e CT-41. `CadastroParticipanteTest.php:30` foi ajustado. |
| Transferência fora do inventário | Entrou na tabela da seção 1, no CA12, no T1 e no CT-30. |

Recomendados, todos acatados:

- As linhas "**Confirmar.**" das decisões receberam a decisão do humano, e a justificativa de `daIgreja` foi trocada.
- CT-19: usa `recurso=participante` e filtra por `acao` na asserção. Não se cria filtro novo.
- T15 da rodada 1 (multisseleção, agora T16): saiu a frase sobre "testes que hoje cobrem a contingência". Ficou descrita a montagem do teste a partir do zero.
- Limite no portal: entraram os casos vitest CT-42 (`PainelAgenda`) e CT-43 (campo da contingência). O "pronto quando" do T2 não usa mais `grep`.
- `PainelAgenda`: `minLength={3}`, o botão fica desabilitado com menos de 3 caracteres e o rótulo passa a "3 a 247 caracteres".
- CT-3, CT-11 e CT-13: entraram `coordenadora_chefe` e `adm_regional`, além da chefe com escopo que não é `ebi`.
- CT-18: entrou o caso `null` para participante sem igreja.
- CT-35: foi parametrizado com as três respostas do CA2.
- O participante mesclado volta como `not_found` no lote. Isso está documentado na seção 5 e no schema, e está no CT-25.
- O ramo "sem contexto" de `participante.cadastrado` saiu da tabela.

Opcionais:

- **Acatado:** padrão `America/Sao_Paulo` quando `EbiDados.timezone` vem vazio.
- **Acatado:** frase sobre a exposição de presença no lote, em "Segurança e autorização".
- **Acatado:** `npm run gen:api` saiu do T2. (T2 só mexe em limites que o `schema.d.ts` não usa.)
- **Acatado (extra):** casos novos CT-40 (igreja inexistente no cadastro), CT-41 (`daIgreja` com igreja antiga inexistente), CT-37 a CT-39 (território da exceção, incluindo participante sem igreja e `super_admin`).
- **Acatado:** o serviço e o HTTP do lote foram fundidos numa tarefa só (T14). As tarefas foram renumeradas.
- **Recusado:** separar T1-T4 em outra demanda. O humano escolheu entregar as cinco dívidas juntas. Fica registrado, no início da "Solução" e no plano, que T1-T4 podem ir num commit ou PR antes, sem dependência do resto. Isso dá o mesmo ganho sem abrir uma demanda nova.
