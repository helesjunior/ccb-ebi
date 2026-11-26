import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import { obterEscala, registrarPresencaEquipe } from '../api/escalas'
import { EstadoTabela } from '../components/EstadoTabela'
import { Sugestoes } from '../components/Sugestoes'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  formatarDataHora, formatarHora, rotuloSituacaoMembro, rotuloStatus, SUGESTOES_FINALIDADE,
} from '@/lib/formatos'

type Candidato = components['schemas']['CandidatoPresenca']
type Registro = components['schemas']['Presenca']
type Escala = components['schemas']['Escala']

const FINALIDADES = [
  ...SUGESTOES_FINALIDADE,
  { valor: 'registro_presenca', rotulo: 'Registrar presença na sessão' },
]

export function Presenca({ sessaoId, onVoltar, papeis }: {
  sessaoId: string
  onVoltar: () => void
  papeis: string[]
}) {
  const [sessao, setSessao] = useState<components['schemas']['SessaoDetalhe'] | null>(null)
  const [presencas, setPresencas] = useState<Registro[]>([])
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [termo, setTermo] = useState('')
  const [finalidade, setFinalidade] = useState('registro_presenca')
  const [resultados, setResultados] = useState<Candidato[]>([])
  const [buscando, setBuscando] = useState(false)
  const [selecionado, setSelecionado] = useState<Candidato | null>(null)
  const [modoContingencia, setModoContingencia] = useState(false)
  const [motivoContingencia, setMotivoContingencia] = useState('')
  const [estorno, setEstorno] = useState<Registro | null>(null)
  const [motivoEstorno, setMotivoEstorno] = useState('')
  const [escala, setEscala] = useState<Escala | null>(null)
  const [presencaEquipe, setPresencaEquipe] = useState<Record<string, boolean>>({})
  const [erroEquipe, setErroEquipe] = useState<string | null>(null)
  const consulta = useRef<AbortController | null>(null)
  const consultaBusca = useRef<AbortController | null>(null)
  const podeGerenciar = papeis.some((p) => ['adm_igreja', 'coordenadora', 'coordenadora_chefe'].includes(p))
  const podeRegistrar = papeis.some((p) => ['coordenadora', 'coordenadora_chefe', 'auxiliar'].includes(p))
  const podeContingencia = sessao?.status === 'encerrada' && podeGerenciar
  const emContingencia = modoContingencia && podeContingencia
  const podeConfirmar = emContingencia || (sessao?.status === 'aberta' && podeRegistrar)
  const mostrarBusca = sessao?.status === 'aberta' || emContingencia
  const motivoContingenciaValido = motivoContingencia.trim().length >= 3 && motivoContingencia.trim().length <= 500
  const motivoEstornoValido = motivoEstorno.trim().length >= 3 && motivoEstorno.trim().length <= 500

  const carregar = useCallback(async () => {
    consulta.current?.abort()
    const controller = new AbortController()
    consulta.current = controller
    const options = { params: { path: { id: sessaoId } }, headers: cabecalhos(), signal: controller.signal }
    setCarregando(true)
    setErro(null)
    try {
      const [detalhe, lista] = await Promise.all([
        api.GET('/sessions/{id}', options),
        api.GET('/sessions/{id}/attendance', {
          ...options,
          params: { path: { id: sessaoId }, query: { finalidade: 'registro_presenca' } },
        }),
      ])
      if (controller.signal.aborted) return
      if (detalhe.error || !detalhe.data) throw detalhe.error
      if (lista.error || !lista.data) throw lista.error
      setSessao(detalhe.data)
      setPresencas(lista.data.data ?? [])
    } catch (error) {
      if (!controller.signal.aborted) setErro(lerErro(error, 'Não foi possível carregar a presença da sessão.').detail)
    } finally {
      if (!controller.signal.aborted) setCarregando(false)
    }
  }, [sessaoId])

  useEffect(() => {
    setSessao(null)
    setPresencas([])
    setTermo('')
    setSelecionado(null)
    setModoContingencia(false)
    setMotivoContingencia('')
    setEstorno(null)
    setEscala(null)
    setPresencaEquipe({})
    setErroEquipe(null)
    void carregar()
    return () => consulta.current?.abort()
  }, [carregar])

  useEffect(() => {
    if (sessao === null) return
    void carregarEquipeDaEscala(sessao.escala_id ?? null)
  }, [sessao])

  useEffect(() => {
    const controller = new AbortController()
    consultaBusca.current = controller
    setResultados([])
    setBuscando(false)
    if (!mostrarBusca || termo.trim().length < 3 || !finalidade.trim()) return () => controller.abort()
    setBuscando(true)
    const timer = setTimeout(async () => {
      try {
        const { data, error } = await api.GET('/sessions/{id}/participants', {
          params: { path: { id: sessaoId }, query: { busca: termo.trim(), finalidade: finalidade.trim() } },
          headers: cabecalhos(), signal: controller.signal,
        })
        if (controller.signal.aborted) return
        if (error || !data) throw error
        setResultados(data.data ?? [])
      } catch (error) {
        if (!controller.signal.aborted) setErro(lerErro(error, 'Não foi possível buscar participantes.').detail)
      } finally {
        if (!controller.signal.aborted) setBuscando(false)
      }
    }, 300)
    return () => { clearTimeout(timer); controller.abort() }
  }, [sessaoId, termo, finalidade, mostrarBusca])

  function limparBusca() {
    consultaBusca.current?.abort()
    setResultados([])
    setSelecionado(null)
    setBuscando(false)
  }

  async function confirmar() {
    if (enviando || carregando || !podeConfirmar || !selecionado?.id || !selecionado.elegivel) return
    if (emContingencia && !motivoContingenciaValido) return
    setEnviando(true)
    setErro(null)
    const options = { params: { path: { id: sessaoId } }, headers: cabecalhos() }
    try {
      const resposta = emContingencia
        ? await api.POST('/sessions/{id}/contingency-attendance', {
          ...options, body: { participante_id: selecionado.id, motivo: motivoContingencia.trim() },
        })
        : await api.POST('/sessions/{id}/attendance', {
          ...options, body: { participante_id: selecionado.id, origem: 'web' },
        })
      if (resposta.error) throw resposta.error
      toast.success('Presença confirmada.')
      limparBusca()
      setTermo('')
      setMotivoContingencia('')
      await carregar()
    } catch (error) {
      setErro(lerErro(error, 'Não foi possível registrar a presença.').detail)
    } finally {
      setEnviando(false)
    }
  }

  async function estornar(evento: FormEvent) {
    evento.preventDefault()
    if (enviando || !podeGerenciar || !estorno?.id || !motivoEstornoValido) return
    setEnviando(true)
    setErro(null)
    try {
      const { error } = await api.POST('/attendance/{id}/reversal', {
        params: { path: { id: estorno.id } }, headers: cabecalhos(), body: { motivo: motivoEstorno.trim() },
      })
      if (error) throw error
      toast.success('Presença estornada.')
      setEstorno(null)
      limparBusca()
      setTermo('')
      await carregar()
    } catch (error) {
      setErro(lerErro(error, 'Não foi possível estornar a presença.').detail)
    } finally {
      setEnviando(false)
    }
  }

  const equipe = sessao?.equipe_minima
  const resumoEquipe = equipe
    ? `Equipe mínima: ${equipe.presentes ?? 0} presentes exigem ${equipe.auxiliares_necessarios ?? 0} auxiliares; ${equipe.auxiliares_informados ?? 'não'} informados.`
    : ''
  // A presença da equipe só faz sentido com a escala aprovada e a sessão
  // encerrada; a marcação parte do que foi confirmado antes da sessão.
  const vagasDaEquipe = (escala?.membros ?? []).filter((m) => m.id && m.status !== 'substituido')
  // Quem lê sem gerir vê a equipe, mas não marca nem envia.
  const podeEditarEquipe = escala?.pode_gerir === true
  const mostrarEquipe =
    sessao?.status === 'encerrada' && podeGerenciar && escala?.status === 'aprovada' && vagasDaEquipe.length > 0
  const pendenteEquipe = escala?.presenca_equipe_pendente === true

  async function carregarEquipeDaEscala(escalaId: string | null, manterMarcacoes = false) {
    if (!escalaId) {
      setEscala(null)
      setPresencaEquipe({})
      return
    }
    const { data } = await obterEscala(escalaId)
    setEscala(data ?? null)
    setPresencaEquipe((anteriores) => {
      const inicio: Record<string, boolean> = {}
      for (const membro of data?.membros ?? []) {
        if (!membro.id) continue
        // Depois de um 422 a vaga saiu: mantém o que a pessoa já marcou.
        inicio[membro.id] = manterMarcacoes && membro.id in anteriores
          ? anteriores[membro.id]
          : membro.status === 'confirmado' || membro.presente === true
      }
      return inicio
    })
  }

  async function enviarPresencaEquipe() {
    if (!escala?.id || enviando || !podeEditarEquipe) return
    setEnviando(true)
    setErroEquipe(null)
    const { data, error } = await registrarPresencaEquipe(
      escala.id,
      vagasDaEquipe.map((m) => ({ id: m.id!, presente: presencaEquipe[m.id!] === true })),
    )
    setEnviando(false)
    if (error) {
      // O formulário fica como está para a pessoa corrigir e reenviar.
      setErroEquipe(lerErro(error, 'Não foi possível registrar a presença da equipe.').detail)
      // A vaga pode ter saído enquanto a tela estava aberta: recarrega a
      // equipe mantendo o que já foi marcado.
      if (lerErro(error, '').code === 'membro_invalido') {
        await carregarEquipeDaEscala(escala.id, true)
      }
      return
    }
    setEscala(data ?? null)
    toast.success('Presença da equipe registrada.')
  }

  return (
    <section className="mx-auto flex max-w-5xl flex-col gap-6 pb-32">
      <header className="flex flex-col items-start gap-3">
        <Button type="button" variant="outline" onClick={onVoltar} disabled={enviando}>Voltar para sessões</Button>
        <h1 className="text-2xl font-semibold">Presença{sessao && ` — ${sessao.ebi_nome ?? 'EBI'}`}</h1>
        {sessao && <div className="flex flex-wrap items-center gap-3">
          <p>{formatarDataHora(sessao.inicio_previsto, sessao.timezone)}</p>
          <Badge variant="outline">{rotuloStatus(sessao.status)}</Badge>
        </div>}
      </header>
      {erro && !estorno && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
      {sessao?.status === 'cancelada' ? (
        <Alert><AlertDescription>Sessão cancelada: {sessao.motivo_cancelamento || 'Motivo não informado.'}</AlertDescription></Alert>
      ) : equipe && (
        equipe.alerta
          ? <Alert role="status"><AlertDescription>{resumoEquipe}</AlertDescription></Alert>
          : <p className="text-sm text-muted-foreground">{resumoEquipe}</p>
      )}
      {podeContingencia && <Button type="button" variant="outline" className="self-start" disabled={enviando || carregando} onClick={() => {
        limparBusca()
        setTermo('')
        setMotivoContingencia('')
        setModoContingencia(!modoContingencia)
      }}>{emContingencia ? 'Sair da contingência' : 'Lançar lista em papel'}</Button>}
      {emContingencia && <Alert role="status" className="border-amber-500">
        <AlertDescription className="flex flex-col gap-3">
          <p>Modo contingência: lançando lista em papel de uma sessão encerrada. Informe o motivo de cada presença.</p>
          <Label htmlFor="contingencia-motivo">Motivo da contingência (3 a 500 caracteres)</Label>
          <Input id="contingencia-motivo" value={motivoContingencia} onChange={(e) => setMotivoContingencia(e.target.value)} minLength={3} maxLength={500} required disabled={enviando} />
        </AlertDescription>
      </Alert>}
      {mostrarBusca && <Card>
        <CardHeader><CardTitle>Buscar participante</CardTitle></CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form role="search" onSubmit={(e) => e.preventDefault()} className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-2">
              <Label htmlFor="presenca-busca">Nome ou matrícula</Label>
              <Input id="presenca-busca" type="search" autoComplete="off" placeholder="Digite pelo menos 3 caracteres" value={termo} disabled={enviando || carregando} onChange={(e) => {
                limparBusca()
                setErro(null)
                setTermo(e.target.value)
              }} />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="presenca-finalidade">Finalidade da leitura</Label>
              <Input id="presenca-finalidade" list="presenca-finalidades" value={finalidade} required disabled={enviando || carregando} onChange={(e) => {
                limparBusca()
                setErro(null)
                setFinalidade(e.target.value)
              }} />
              <Sugestoes id="presenca-finalidades" opcoes={FINALIDADES} />
            </div>
          </form>
          <div aria-live="polite" aria-busy={buscando}>
            <Table className="table-fixed">
              <TableHeader><TableRow><TableHead>Nome</TableHead><TableHead>Matrícula</TableHead><TableHead>Igreja de referência</TableHead><TableHead>Elegibilidade</TableHead></TableRow></TableHeader>
              <TableBody>
                <EstadoTabela colunas={4} carregando={buscando} vazio={resultados.length === 0} mensagemVazio={termo.trim().length < 3 ? 'Digite pelo menos 3 caracteres para buscar.' : 'Nenhum participante encontrado.'} />
                {!buscando && resultados.map((candidato) => <TableRow key={candidato.id} data-state={selecionado?.id === candidato.id ? 'selected' : undefined}>
                  <TableCell colSpan={4} className="p-0">
                    <button type="button" className="grid min-h-14 w-full grid-cols-4 items-center gap-3 p-3 text-left break-words whitespace-normal hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-70" disabled={enviando || carregando || !candidato.elegivel || !candidato.id || !podeConfirmar} aria-pressed={selecionado?.id === candidato.id} onClick={() => setSelecionado(candidato)}>
                      <span className="font-medium">{candidato.nome}</span>
                      <span>{candidato.matricula}</span>
                      <span>{candidato.igreja_referencia?.nome ?? '—'}</span>
                      <span>{candidato.elegivel
                        ? <Badge className="bg-green-700 text-white">Elegível</Badge>
                        : <Badge variant="destructive" className="whitespace-normal">Bloqueado: {candidato.motivo_bloqueio ?? 'Motivo não informado'}</Badge>}</span>
                    </button>
                  </TableCell>
                </TableRow>)}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>}
      <Card>
        <CardHeader><CardTitle>Presentes</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader><TableRow><TableHead>Hora</TableHead><TableHead>Nome</TableHead><TableHead>Matrícula</TableHead><TableHead>Registro</TableHead><TableHead>Ações</TableHead></TableRow></TableHeader>
            <TableBody>
              <EstadoTabela colunas={5} carregando={carregando} vazio={presencas.length === 0} mensagemVazio="Nenhuma presença registrada." />
              {!carregando && presencas.map((presenca) => <TableRow key={presenca.id}>
                <TableCell className={presenca.status === 'estornada' ? 'line-through' : ''}>{formatarHora(presenca.registrado_em, sessao?.timezone)}</TableCell>
                <TableCell className={`whitespace-normal ${presenca.status === 'estornada' ? 'line-through' : ''}`}>{presenca.participante?.nome}</TableCell>
                <TableCell className={presenca.status === 'estornada' ? 'line-through' : ''}>{presenca.participante?.matricula}</TableCell>
                <TableCell className="whitespace-normal">
                  {presenca.origem === 'contingencia' && <Badge variant="outline">contingência</Badge>}
                  {presenca.status === 'estornada' && <p className="text-sm text-muted-foreground">Estornada: {presenca.motivo_estorno}</p>}
                </TableCell>
                <TableCell>{podeGerenciar && sessao?.status !== 'cancelada' && presenca.status === 'valida' && presenca.id && <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => {
                  setErro(null)
                  setMotivoEstorno('')
                  setEstorno(presenca)
                }}>Estornar</Button>}</TableCell>
              </TableRow>)}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {mostrarEquipe && (
        <Card>
          <CardHeader>
            <CardTitle>Presença da equipe</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {pendenteEquipe && (
              <Alert role="status"><AlertDescription>Presença da equipe não registrada.</AlertDescription></Alert>
            )}
            {erroEquipe && <Alert variant="destructive" role="alert"><AlertDescription>{erroEquipe}</AlertDescription></Alert>}
            <p className="text-sm text-muted-foreground">
              As caixas já vêm marcadas para quem confirmou a escala; confira com a sessão e envie.
            </p>
            <Table>
              <TableHeader><TableRow><TableHead>Pessoa</TableHead><TableHead>Função</TableHead><TableHead>Confirmou</TableHead><TableHead>Esteve</TableHead></TableRow></TableHeader>
              <TableBody>
                {vagasDaEquipe.map((membro) => (
                  <TableRow key={membro.id}>
                    <TableCell className="whitespace-normal">{membro.nome ?? '—'}</TableCell>
                    <TableCell>{membro.funcao === 'coordenadora' ? 'Coordenação' : 'Auxiliar'}</TableCell>
                    <TableCell>{rotuloSituacaoMembro(membro.status)}</TableCell>
                    <TableCell>
                      <input
                        type="checkbox"
                        className="size-4 accent-primary"
                        aria-label={`Esteve: ${membro.nome ?? 'membro da equipe'}`}
                        checked={presencaEquipe[membro.id!] === true}
                        disabled={enviando || !podeEditarEquipe}
                        onChange={(e) => setPresencaEquipe({ ...presencaEquipe, [membro.id!]: e.target.checked })}
                      />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {podeEditarEquipe && <Button type="button" className="self-start" disabled={enviando} onClick={() => void enviarPresencaEquipe()}>
              {enviando ? 'Registrando…' : 'Registrar presença da equipe'}
            </Button>}
          </CardContent>
        </Card>
      )}
      {selecionado && mostrarBusca && podeConfirmar && <div className="fixed inset-x-0 bottom-0 z-30 flex flex-wrap items-center justify-center gap-3 border-t bg-background p-4 shadow-lg">
        <Button type="button" variant="outline" disabled={enviando} onClick={() => setSelecionado(null)}>Cancelar seleção</Button>
        <Button type="button" className="h-auto min-h-11 whitespace-normal" disabled={enviando || carregando || (emContingencia && !motivoContingenciaValido)} onClick={() => void confirmar()}>
          {enviando ? 'Registrando…' : `Confirmar presença de ${selecionado.nome}`}
        </Button>
      </div>}
      <Dialog open={Boolean(estorno)} onOpenChange={(aberto) => { if (!aberto && !enviando) setEstorno(null) }}>
        <DialogContent showCloseButton={!enviando}>
          <DialogHeader><DialogTitle>Estornar presença</DialogTitle><DialogDescription>Informe o motivo para estornar a presença de {estorno?.participante?.nome}.</DialogDescription></DialogHeader>
          {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
          <form onSubmit={estornar} className="flex flex-col gap-4">
            <Label htmlFor="estorno-motivo">Motivo (3 a 500 caracteres)</Label>
            <Input id="estorno-motivo" value={motivoEstorno} onChange={(e) => setMotivoEstorno(e.target.value)} minLength={3} maxLength={500} required disabled={enviando} />
            <DialogFooter>
              <Button type="button" variant="outline" disabled={enviando} onClick={() => setEstorno(null)}>Voltar</Button>
              <Button type="submit" variant="destructive" disabled={enviando || !motivoEstornoValido}>{enviando ? 'Estornando…' : 'Confirmar estorno'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </section>
  )
}
