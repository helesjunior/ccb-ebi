import { useEffect, useRef, useState, type FormEvent } from 'react'
import { BarChart3Icon, ChevronRightIcon, DownloadIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type ContextoAtuacao } from '../api/sessao'
import { EstadoTabela } from '../components/EstadoTabela'
import { formatarData, hojeIso } from '@/lib/formatos'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  explicacaoIndicador,
  periodoPadrao,
  rotuloNivel,
  textoIndicador,
  type ChaveIndicador,
  type ItemIndicador,
  type NoRelatorio,
  type RelatorioPresenca,
  type TotaisRelatorio,
} from './relatorios/indicadores'

/** Indicadores do período (data da sessão). */
const PERIODO: ItemIndicador[] = [
  { chave: 'participantes_distintos', rotulo: 'Participantes distintos' },
  { chave: 'presencas', rotulo: 'Presenças' },
  { chave: 'sessoes_realizadas', rotulo: 'Sessões realizadas' },
  { chave: 'media_por_sessao', rotulo: 'Média por sessão' },
]

/** Indicadores da data de geração (mostrados à parte). */
const HOJE: ItemIndicador[] = [
  { chave: 'participantes_cadastrados', rotulo: 'Participantes cadastrados' },
  { chave: 'participantes_ativos', rotulo: 'Participantes ativos' },
  { chave: 'igrejas_com_ebi_ativo', rotulo: 'Igrejas com EBI ativo' },
]

interface Trilha {
  no: NoRelatorio
  filhos: NoRelatorio[]
}

/** Valor de um indicador com a explicação da supressão no título. */
function CelulaIndicador({ totais, chave }: { totais: TotaisRelatorio; chave: ChaveIndicador }) {
  const indicador = totais[chave]
  const explicacao = explicacaoIndicador(indicador)
  return <TableCell title={explicacao}>{textoIndicador(indicador)}</TableCell>
}

/** Monta a consulta do nó: sem nó explícito, o servidor usa o contexto. */
function consultar(no: NoRelatorio | null, janela: { inicio: string; fim: string }) {
  const parametros = new URLSearchParams({ inicio: janela.inicio, fim: janela.fim })
  if (no) {
    parametros.set('nivel', no.nivel)
    parametros.set('id', no.id)
  }
  return parametros
}

/**
 * Relatórios básicos (RF-14). O servidor calcula e decide a supressão; a tela só
 * troca os rótulos (`< 5`, "Não divulgado", "—") e oferece período, trilha e
 * exportação CSV. Nenhum nome de pessoa aparece: as linhas são nós da árvore.
 */
export function Relatorios({ contexto }: { contexto: ContextoAtuacao | null }) {
  const padrao = periodoPadrao(hojeIso())
  const [inicio, setInicio] = useState(padrao.inicio)
  const [fim, setFim] = useState(padrao.fim)
  const [periodo, setPeriodo] = useState(padrao)
  const [trilha, setTrilha] = useState<Trilha[]>([])
  const [relatorio, setRelatorio] = useState<RelatorioPresenca | null>(null)
  const [carregando, setCarregando] = useState(true)
  const [exportando, setExportando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  /** Última consulta pedida; resposta de consulta antiga é descartada. */
  const sequencia = useRef(0)

  /** Identidade do contexto: trocar de escopo descarta relatório e trilha. */
  const chaveContexto = contexto
    ? `${contexto.papel}:${contexto.tipo_escopo}:${contexto.escopo_id}`
    : ''

  // O que a tela mostra: a raiz do contexto ou o filho aberto por último.
  const [alvo, setAlvo] = useState<NoRelatorio | null>(null)

  // Nó, período e contexto mudam a consulta: a raiz do novo contexto é
  // recarregada e o nó aberto também, quando só a janela muda.
  useEffect(() => {
    const pedido = ++sequencia.current
    setCarregando(true)
    setErro(null)
    void (async () => {
      const { data, error } = await api.GET('/reports/attendance', {
        headers: cabecalhos(),
        params: { query: Object.fromEntries(consultar(alvo, periodo)) as never },
      })
      if (pedido !== sequencia.current) return
      setCarregando(false)
      // O 200 é `string` no spec por causa do ramo CSV; aqui só chega JSON.
      if (error || !data || typeof data === 'string') {
        setRelatorio(null)
        setErro(lerErro(error, 'Não foi possível carregar o relatório.').detail)
        return
      }
      setRelatorio(data)
    })()
  }, [alvo, periodo, chaveContexto])

  // Troca de escopo: some com a tela anterior antes de pedir a raiz nova.
  useEffect(() => {
    setAlvo(null)
    setRelatorio(null)
    setErro(null)
    setTrilha([])
  }, [chaveContexto])

  /** Recarrega o nó exibido no período já aplicado (mesmo período ou erro). */
  function recarregarNoAtual() {
    const pedido = ++sequencia.current
    setCarregando(true)
    setErro(null)
    void (async () => {
      const { data, error } = await api.GET('/reports/attendance', {
        headers: cabecalhos(),
        params: { query: Object.fromEntries(consultar(alvo, periodo)) as never },
      })
      if (pedido !== sequencia.current) return
      setCarregando(false)
      if (error || !data || typeof data === 'string') {
        setRelatorio(null)
        setErro(lerErro(error, 'Não foi possível carregar o relatório.').detail)
        return
      }
      setRelatorio(data)
    })()
  }

  function abrirFilho(filho: NoRelatorio) {
    setTrilha((anteriores) => [...anteriores, { no: filho, filhos: [] }])
    setAlvo(filho)
  }

  function voltarPara(indice: number) {
    setTrilha((anteriores) => anteriores.slice(0, indice + 1))
    setAlvo(indice < 0 ? null : trilha[indice].no)
  }

  function aplicarPeriodo(evento: FormEvent) {
    evento.preventDefault()
    if (!inicio || !fim || inicio > fim) {
      setErro('O início precisa ser anterior ou igual ao fim.')
      return
    }
    // Aplica o período na unidade aberta: só a janela muda, o nó continua.
    if (inicio === periodo.inicio && fim === periodo.fim) {
      // Mesmo período: o efeito não roda, então recarrega aqui.
      recarregarNoAtual()
      return
    }
    setPeriodo({ inicio, fim })
  }

  async function exportar() {
    if (!relatorio) return
    setExportando(true)
    const parametros = consultar(alvo, periodo)
    parametros.set('formato', 'csv')
    try {
      // O CSV é `text/csv` no spec: sem `parseAs`, o cliente tentaria JSON.
      const { data, error } = await api.GET('/reports/attendance', {
        headers: cabecalhos(),
        params: { query: Object.fromEntries(parametros) as never },
        parseAs: 'text',
      })
      if (error || !data) {
        setErro(lerErro(error, 'Não foi possível exportar o relatório.').detail)
        return
      }
      baixarCsv(data, `relatorio-presenca-${relatorio.no.nivel}-${periodo.inicio}_${periodo.fim}.csv`)
    } finally {
      setExportando(false)
    }
  }

  return (
    <section className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BarChart3Icon className="size-5" aria-hidden="true" />
            Relatórios de presença
          </CardTitle>
          <CardDescription>
            Totais do período por nível. Ninguém é identificado: unidades pequenas não divulgam
            números de pessoas.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form className="flex flex-wrap items-end gap-3" onSubmit={aplicarPeriodo}>
            <div className="flex flex-col gap-1">
              <Label htmlFor="relatorio-inicio">Início</Label>
              <Input
                id="relatorio-inicio"
                type="date"
                value={inicio}
                onChange={(e) => setInicio(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-1">
              <Label htmlFor="relatorio-fim">Fim</Label>
              <Input
                id="relatorio-fim"
                type="date"
                value={fim}
                onChange={(e) => setFim(e.target.value)}
              />
            </div>
            <Button type="submit" variant="secondary">
              Aplicar período
            </Button>
            <Button
              type="button"
              onClick={() => void exportar()}
              disabled={!relatorio || exportando}
            >
              <DownloadIcon className="size-4" aria-hidden="true" />
              Exportar CSV
            </Button>
          </form>

          {erro && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}

          <nav aria-label="Trilha do relatório" className="flex flex-wrap items-center gap-1 text-sm">
            <Button
              type="button"
              variant={trilha.length === 0 ? 'default' : 'ghost'}
              size="sm"
              onClick={() => voltarPara(-1)}
            >
              {contexto ? rotuloNivel(contexto.tipo_escopo) : 'Minha unidade'}
            </Button>
            {trilha.map((passo, indice) => (
              <span key={passo.no.id} className="flex items-center gap-1">
                <ChevronRightIcon className="size-4 text-muted-foreground" aria-hidden="true" />
                <Button
                  type="button"
                  variant={indice === trilha.length - 1 ? 'default' : 'ghost'}
                  size="sm"
                  onClick={() => voltarPara(indice)}
                >
                  {passo.no.nome}
                </Button>
              </span>
            ))}
          </nav>
        </CardContent>
      </Card>

      {relatorio && (
        <Card>
          <CardHeader>
            <CardTitle>
              {relatorio.no.nome} <span className="text-muted-foreground">({rotuloNivel(relatorio.no.nivel)})</span>
            </CardTitle>
            <CardDescription>
              {formatarData(relatorio.periodo.inicio)} a {formatarData(relatorio.periodo.fim)}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-6">
            <div className="grid gap-6 md:grid-cols-2">
              <Indicadores titulo="No período" itens={PERIODO} totais={relatorio.totais} />
              <Indicadores titulo="Na data de hoje" itens={HOJE} totais={relatorio.totais} />
            </div>

            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Unidade</TableHead>
                    {PERIODO.map((i) => (
                      <TableHead key={i.chave}>{i.rotulo}</TableHead>
                    ))}
                    {HOJE.map((i) => (
                      <TableHead key={i.chave}>{i.rotulo}</TableHead>
                    ))}
                    <TableHead className="sr-only">Abrir</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <EstadoTabela
                    colunas={2 + PERIODO.length + HOJE.length}
                    carregando={carregando}
                    vazio={(relatorio.filhos ?? []).length === 0}
                    mensagemVazio="Esta unidade não tem subdivisões no relatório."
                  />
                  {(relatorio.filhos ?? []).map((filho) => (
                    <TableRow key={filho.id}>
                      <TableCell>
                        <Button
                          type="button"
                          variant="link"
                          className="h-auto p-0"
                          onClick={() => abrirFilho({ nivel: filho.nivel, id: filho.id, nome: filho.nome })}
                        >
                          {filho.nome} <span className="text-muted-foreground">({rotuloNivel(filho.nivel)})</span>
                        </Button>
                      </TableCell>
                      {PERIODO.map((i) => (
                        <CelulaIndicador key={i.chave} totais={filho.totais} chave={i.chave} />
                      ))}
                      {HOJE.map((i) => (
                        <CelulaIndicador key={i.chave} totais={filho.totais} chave={i.chave} />
                      ))}
                      <TableCell className="text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          aria-label={`Abrir ${filho.nome}`}
                          onClick={() => abrirFilho({ nivel: filho.nivel, id: filho.id, nome: filho.nome })}
                        >
                          <ChevronRightIcon className="size-4" aria-hidden="true" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <footer className="flex flex-col gap-1 border-t pt-4 text-xs text-muted-foreground">
              <p>Fuso considerado: {relatorio.fuso}.</p>
              <p>{relatorio.criterios}</p>
              <p>Relatório gerado em {formatarData(relatorio.gerado_em)}.</p>
            </footer>
          </CardContent>
        </Card>
      )}

      {!relatorio && carregando && <p role="status">Carregando…</p>}
      {!relatorio && !carregando && !erro && (
        <p className="text-muted-foreground">Nenhum relatório disponível para esta unidade.</p>
      )}
    </section>
  )
}

function Indicadores({
  titulo,
  itens,
  totais,
}: {
  titulo: string
  itens: ItemIndicador[]
  totais: TotaisRelatorio
}) {
  return (
    <section className="flex flex-col gap-2" aria-label={titulo}>
      <h3 className="text-sm font-medium">{titulo}</h3>
      <dl className="grid grid-cols-2 gap-2">
        {itens.map((item) => (
          <div key={item.chave} className="rounded-md border p-3">
            <dt className="text-xs text-muted-foreground">{item.rotulo}</dt>
            <dd
              className="text-lg font-semibold"
              title={explicacaoIndicador(totais[item.chave])}
            >
              {textoIndicador(totais[item.chave])}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}

/** Dispara o download do CSV já vindo do servidor (mesmo cálculo e supressão). */
function baixarCsv(conteudo: string, nome: string) {
  const url = URL.createObjectURL(new Blob([conteudo], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = nome
  link.click()
  URL.revokeObjectURL(url)
}
