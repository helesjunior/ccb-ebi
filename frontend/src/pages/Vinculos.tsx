import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { LinkIcon, SearchIcon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { EstadoTabela } from '../components/EstadoTabela'
import { Obrigatorio } from '../components/Obrigatorio'
import { Sugestoes } from '../components/Sugestoes'
import { formatarData, rotuloStatus, SUGESTOES_RELACAO } from '@/lib/formatos'
import { cabecalhos, type ContextoAtuacao } from '../api/sessao'
import type { components, paths } from '../api/schema'
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
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

type Vinculo = components['schemas']['ResponsavelParticipante']
type Participante = components['schemas']['Participante']
type Decisao = components['schemas']['DecisaoVinculoInput']['decisao']
/** Enum de situação do contrato (`validacao`), sem cast. */
type Situacao = NonNullable<
  NonNullable<paths['/guardian-requests']['get']['parameters']['query']>['validacao']
>

/** Finalidade declarada da listagem de pedidos (registrada na auditoria). */
const FINALIDADE = 'triagem_vinculo'
/** Finalidade declarada da busca por nome na aba Solicitar. */
const FINALIDADE_BUSCA = 'cadastro_vinculo'

/** Ações de decisão oferecidas por linha, na ordem em que aparecem. */
const DECISOES: { valor: Decisao; rotulo: string }[] = [
  { valor: 'aprovar', rotulo: 'Aprovar' },
  { valor: 'recusar', rotulo: 'Recusar' },
  { valor: 'pedir_documento', rotulo: 'Pedir documento' },
  { valor: 'revogar', rotulo: 'Revogar' },
]

const SITUACOES: { valor: Situacao; rotulo: string }[] = [
  { valor: 'pendente', rotulo: 'Pendentes' },
  { valor: 'aprovada', rotulo: 'Aprovados' },
  { valor: 'aguardando_documento', rotulo: 'Aguardando documento' },
  { valor: 'recusada', rotulo: 'Recusados' },
  { valor: 'todos', rotulo: 'Todos' },
]

/** Papéis que leem o cadastro de participantes (`participante.ler`). */
const PAPEIS_CADASTRO = [
  'super_admin',
  'adm_regional',
  'adm_setor',
  'adm_igreja',
  'coordenadora',
  'auxiliar',
  'ministerio_local',
]

/** Papéis com `vinculo.gerenciar`: veem o território e decidem. */
const PAPEIS_GESTAO_VINCULO = [
  'super_admin',
  'adm_regional',
  'adm_setor',
  'adm_igreja',
  'coordenadora',
  'auxiliar',
  'ministerio_local',
]

/**
 * A busca por nome exige `participante.ler`. O `responsavel` **não** tem esse
 * escopo: a busca daria 403, então a aba Solicitar não aparece para ele — o
 * caminho dele é o cadastro assistido, feito pela coordenação.
 */
function podeBuscarParticipante(escopos: ContextoAtuacao[]): boolean {
  return escopos.some((e) => PAPEIS_CADASTRO.includes(e.papel ?? ''))
}

/** Quem decide pedidos de vínculo; para os demais a lista é só leitura. */
function podeDecidirVinculo(escopos: ContextoAtuacao[]): boolean {
  return escopos.some((e) => PAPEIS_GESTAO_VINCULO.includes(e.papel ?? ''))
}

/**
 * Ações válidas para a linha. Vínculo encerrado (`fim`) só aceita revogar de
 * novo — aprovar um encerrado o reabriria mantendo `fim`, sem dar acesso.
 */
function acoesDoPedido(pedido: Vinculo): typeof DECISOES {
  if (pedido.fim) {
    return DECISOES.filter((d) => d.valor === 'revogar')
  }
  if (pedido.validacao === 'recusada') {
    return DECISOES.filter((d) => d.valor === 'aprovar')
  }
  return DECISOES
}

/**
 * Vínculos: pedidos de vínculo do responsável e decisão da coordenadora (RF-03).
 *
 * Duas abas. *Pedidos* lista o que o servidor considera visível ao ator — o
 * território dele para quem gerencia, os pedidos que ele mesmo abriu para os
 * demais — e permite decidir por botão. *Solicitar* escolhe o participante pelo
 * nome: o produto nunca pede ULID ao usuário. A tela não calcula escopo: só
 * esconde o que o servidor recusaria de qualquer forma.
 */
export function Vinculos({ escopos }: { escopos: ContextoAtuacao[] }) {
  const [aba, setAba] = useState<string>('pedidos')

  const [validacao, setValidacao] = useState<Situacao>('pendente')
  const [pedidos, setPedidos] = useState<Vinculo[]>([])
  const [carregando, setCarregando] = useState(true)
  const [ocupado, setOcupado] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const gerencia = podeDecidirVinculo(escopos)

  const carregar = useCallback(async () => {
    setCarregando(true)
    const { data, error } = await api.GET('/guardian-requests', {
      params: { query: { validacao, finalidade: FINALIDADE } },
      headers: cabecalhos(),
    })
    setCarregando(false)
    if (error || !data) {
      setPedidos([])
      setErro(lerErro(error, 'Não foi possível carregar os pedidos de vínculo.').detail)
      return
    }
    setErro(null)
    setPedidos(Array.isArray(data) ? data : (data.data ?? []))
  }, [validacao])

  useEffect(() => {
    void carregar()
  }, [carregar])

  async function decidir(id: string, decisao: Decisao) {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.PATCH('/guardian-requests/{id}', {
      params: { path: { id } },
      headers: cabecalhos(),
      body: { decisao },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível decidir o vínculo.').detail)
      return
    }
    toast.success(`Vínculo atualizado: ${rotuloStatus(data.validacao).toLowerCase()}.`)
    void carregar()
  }

  return (
    <section className="mx-auto flex max-w-5xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}

      <Tabs value={aba} onValueChange={setAba}>
        <TabsList aria-label="Vínculos">
          <TabsTrigger value="pedidos">Pedidos</TabsTrigger>
          {podeBuscarParticipante(escopos) && (
            <TabsTrigger value="solicitar">Solicitar</TabsTrigger>
          )}
        </TabsList>

        <TabsContent value="pedidos">
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <LinkIcon className="size-5" aria-hidden="true" />
                Pedidos de vínculo
              </CardTitle>
              <CardDescription>
                Pedidos visíveis para você. A aprovação libera o acesso do responsável.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <div className="flex flex-col gap-2 sm:max-w-xs">
                <Label htmlFor="vinculos-situacao">Situação</Label>
                <Select
                  value={validacao}
                  onValueChange={(v) => setValidacao(v as Situacao)}
                >
                  <SelectTrigger id="vinculos-situacao" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {SITUACOES.map((s) => (
                      <SelectItem key={s.valor} value={s.valor}>
                        {s.rotulo}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <Table data-testid="pedidos-vinculo">
                <TableHeader>
                  <TableRow>
                    <TableHead>Criança</TableHead>
                    <TableHead>Responsável</TableHead>
                    <TableHead>Relação</TableHead>
                    <TableHead>Situação</TableHead>
                    <TableHead>Solicitado em</TableHead>
                    {gerencia && <TableHead>Ações</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <EstadoTabela
                    colunas={gerencia ? 6 : 5}
                    carregando={carregando}
                    vazio={pedidos.length === 0}
                    mensagemVazio="Nenhum pedido de vínculo."
                  />
                  {pedidos.map((pedido) => (
                    <TableRow key={pedido.id}>
                      <TableCell className="font-medium whitespace-normal">
                        {pedido.participante?.nome ?? '—'}
                        {pedido.participante?.matricula && (
                          <code className="ml-2 text-xs text-muted-foreground">
                            {pedido.participante.matricula}
                          </code>
                        )}
                      </TableCell>
                      <TableCell className="whitespace-normal">{pedido.nome}</TableCell>
                      <TableCell>{rotuloStatus(pedido.tipo_relacao)}</TableCell>
                      <TableCell>
                        {rotuloStatus(pedido.validacao)}
                        {pedido.fim ? ` · encerrado em ${formatarData(pedido.fim)}` : ''}
                      </TableCell>
                      <TableCell>{formatarData(pedido.inicio)}</TableCell>
                      {gerencia && (
                        <TableCell>
                          <div className="flex flex-wrap gap-2">
                            {acoesDoPedido(pedido).map((d) => (
                              <Button
                                key={d.valor}
                                type="button"
                                size="sm"
                                variant={d.valor === 'aprovar' ? 'default' : 'outline'}
                                disabled={ocupado}
                                onClick={() => void decidir(pedido.id ?? '', d.valor)}
                              >
                                {d.rotulo}
                              </Button>
                            ))}
                          </div>
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="solicitar">
          <SolicitarVinculo
            onSolicitado={() => {
              setAba('pedidos')
              void carregar()
            }}
          />
        </TabsContent>
      </Tabs>
    </section>
  )
}

/**
 * Aba *Solicitar*: escolhe o participante pelo nome (nunca por ULID) e envia o
 * pedido. O pedido nasce `pendente`; só a aprovação da coordenação libera acesso.
 */
function SolicitarVinculo({ onSolicitado }: { onSolicitado: () => void }) {
  const [termo, setTermo] = useState('')
  const [resultados, setResultados] = useState<Participante[]>([])
  const [buscando, setBuscando] = useState(false)
  const [buscou, setBuscou] = useState(false)
  const [escolhido, setEscolhido] = useState<Participante | null>(null)

  const [nome, setNome] = useState('')
  const [tipoRelacao, setTipoRelacao] = useState('responsavel')
  const [telefone, setTelefone] = useState('')
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  async function buscar(evento: FormEvent) {
    evento.preventDefault()
    if (!termo.trim()) return
    setErro(null)
    setBuscando(true)
    const { data, error } = await api.GET('/participants', {
      params: { query: { q: termo, finalidade: FINALIDADE_BUSCA } },
      headers: cabecalhos(),
    })
    setBuscando(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível buscar participantes.').detail)
      return
    }
    setResultados(Array.isArray(data) ? data : (data.data ?? []))
    setBuscou(true)
  }

  async function solicitar(evento: FormEvent) {
    evento.preventDefault()
    if (!escolhido) return
    setErro(null)
    setEnviando(true)
    const { data, error } = await api.POST('/participants/{id}/guardians', {
      params: { path: { id: escolhido.id ?? '' } },
      headers: cabecalhos(),
      body: { nome, tipo_relacao: tipoRelacao, telefone: telefone || undefined },
    })
    setEnviando(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível solicitar o vínculo.').detail)
      return
    }
    toast.success('Pedido de vínculo enviado. A coordenação vai analisar.')
    setNome('')
    setTelefone('')
    setTermo('')
    setResultados([])
    setBuscou(false)
    setEscolhido(null)
    onSolicitado()
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <LinkIcon className="size-5" aria-hidden="true" />
          Solicitar vínculo
        </CardTitle>
        <CardDescription>
          Busque o participante pelo nome e peça o vínculo de responsável.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {erro && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>{erro}</AlertDescription>
          </Alert>
        )}

        {escolhido ? (
          <form onSubmit={solicitar} className="flex flex-col gap-4">
            <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
              <div>
                <p className="font-medium">{escolhido.nome}</p>
                {escolhido.matricula && (
                  <code className="text-xs text-muted-foreground">{escolhido.matricula}</code>
                )}
              </div>
              <Button type="button" variant="ghost" onClick={() => setEscolhido(null)}>
                Trocar
              </Button>
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="vinculo-nome">
                Seu nome <Obrigatorio />
              </Label>
              <Input
                id="vinculo-nome"
                value={nome}
                onChange={(e) => setNome(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="vinculo-relacao">
                Relação com a criança <Obrigatorio />
              </Label>
              <Sugestoes id="vinculo-relacao-sugestoes" opcoes={SUGESTOES_RELACAO} />
              <Input
                list="vinculo-relacao-sugestoes"
                id="vinculo-relacao"
                value={tipoRelacao}
                onChange={(e) => setTipoRelacao(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="vinculo-telefone">Telefone (opcional)</Label>
              <Input
                id="vinculo-telefone"
                type="tel"
                inputMode="tel"
                autoComplete="tel"
                placeholder="(61) 90000-0000"
                value={telefone}
                onChange={(e) => setTelefone(e.target.value)}
              />
            </div>
            <Button
              type="submit"
              disabled={enviando || !nome || !tipoRelacao}
              className="self-start"
            >
              {enviando ? 'Enviando…' : 'Solicitar vínculo'}
            </Button>
          </form>
        ) : (
          <>
            <form onSubmit={buscar} className="flex flex-col gap-2 sm:flex-row sm:items-end">
              <div className="flex flex-1 flex-col gap-2">
                <Label htmlFor="vinculo-busca">Nome do participante</Label>
                <Input
                  id="vinculo-busca"
                  value={termo}
                  onChange={(e) => setTermo(e.target.value)}
                  placeholder="Ex.: Ana Clara"
                />
              </div>
              <Button type="submit" disabled={buscando || !termo.trim()}>
                <SearchIcon className="size-4" aria-hidden="true" />
                {buscando ? 'Buscando…' : 'Buscar'}
              </Button>
            </form>

            {buscou && resultados.length === 0 && (
              <p className="text-sm text-muted-foreground">Nenhum participante encontrado.</p>
            )}
            {resultados.length > 0 && (
              <ul className="flex flex-col divide-y rounded-lg border">
                {resultados.map((p) => (
                  <li key={p.id}>
                    <button
                      type="button"
                      onClick={() => setEscolhido(p)}
                      className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-muted/50"
                    >
                      <span className="font-medium">{p.nome}</span>
                      {p.matricula && (
                        <code className="text-xs text-muted-foreground">{p.matricula}</code>
                      )}
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}
