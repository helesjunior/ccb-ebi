import { Fragment, useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import { PainelAgenda } from '../components/agenda/PainelAgenda'
import { EstadoTabela } from '../components/EstadoTabela'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { formatarData, formatarHora, hojeIso, rotuloStatus } from '@/lib/formatos'

type Sessao = components['schemas']['Sessao']
type Acao = 'cancelar' | 'encerrar' | 'corrigir'

function daquiQuatroSemanas() {
  const data = new Date()
  data.setDate(data.getDate() + 28)
  return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`
}

export function Sessoes({ onAbrirPresenca, papeis }: {
  onAbrirPresenca: (sessaoId: string) => void
  papeis: string[]
}) {
  const [ebis, setEbis] = useState<components['schemas']['EbiResumo'][]>([])
  const [ebiId, setEbiId] = useState('')
  const [de, setDe] = useState(hojeIso)
  const [ate, setAte] = useState(daquiQuatroSemanas)
  const [sessoes, setSessoes] = useState<Sessao[]>([])
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [dialogo, setDialogo] = useState<{ acao: Acao; sessao: Sessao } | null>(null)
  const [motivo, setMotivo] = useState('')
  const [tema, setTema] = useState('')
  const [passagem, setPassagem] = useState('')
  const [auxiliares, setAuxiliares] = useState('')
  const consulta = useRef<AbortController | null>(null)
  // A sessão carregada (detalhe) diz se a equipe vem da escala aprovada.
  const [equipeDaEscala, setEquipeDaEscala] = useState<boolean | null>(null)
  const podeGerenciar = papeis.some((p) => ['adm_igreja', 'coordenadora', 'coordenadora_chefe'].includes(p))
  const periodoValido = Boolean(de && ate && de <= ate)
  const motivoValido = motivo.trim().length >= 3 && motivo.trim().length <= 500

  useEffect(() => {
    const controller = new AbortController()
    async function carregarEbis() {
      try {
        const { data, error } = await api.GET('/me/ebis', {
          headers: cabecalhos(), signal: controller.signal,
        })
        if (controller.signal.aborted) return
        if (error || !data) throw error
        setEbis(data.data ?? [])
        setEbiId(data.data?.[0]?.id ?? '')
      } catch (error) {
        if (!controller.signal.aborted) {
          setErro(lerErro(error, 'Não foi possível carregar os EBIs.').detail)
        }
      } finally {
        if (!controller.signal.aborted) setCarregando(false)
      }
    }
    void carregarEbis()
    return () => controller.abort()
  }, [])

  const carregar = useCallback(async () => {
    consulta.current?.abort()
    const controller = new AbortController()
    consulta.current = controller
    const { signal } = controller
    if (!ebiId) return
    setSessoes([])
    setErro(null)
    if (!de || !ate || de > ate) {
      setErro('Informe um período válido para consultar as sessões.')
      setCarregando(false)
      return
    }
    setCarregando(true)
    try {
      const { data, error } = await api.GET('/ebis/{id}/sessions', {
        params: { path: { id: ebiId }, query: { de, ate } },
        headers: cabecalhos(), signal,
      })
      if (signal.aborted) return
      if (error || !data) throw error
      const lista = Array.isArray(data) ? data : (data.data ?? [])
      setSessoes(lista as Sessao[])
    } catch (error) {
      if (!signal.aborted) {
        setErro(lerErro(error, 'Não foi possível carregar as sessões.').detail)
      }
    } finally {
      if (!signal.aborted) setCarregando(false)
    }
  }, [ebiId, de, ate])

  useEffect(() => {
    void carregar()
    return () => consulta.current?.abort()
  }, [carregar])

  async function gerar() {
    if (!podeGerenciar || !ebiId || !periodoValido) return
    setErro(null)
    setEnviando(true)
    try {
      const { data, error } = await api.POST('/ebis/{id}/sessions/generate', {
        params: { path: { id: ebiId } }, headers: cabecalhos(), body: { de, ate },
      })
      if (error || !data) throw error
      toast.success(`${data.criadas ?? 0} sessões criadas.`)
      await carregar()
    } catch (error) {
      setErro(lerErro(error, 'Não foi possível gerar as sessões.').detail)
    } finally {
      setEnviando(false)
    }
  }

  function abrirDialogo(acao: Acao, sessao: Sessao) {
    setErro(null)
    setMotivo('')
    setTema(sessao.tema ?? '')
    setPassagem(sessao.passagem_biblica ?? '')
    setAuxiliares(sessao.auxiliares_escalados?.toString() ?? '')
    setEquipeDaEscala(null)
    setDialogo({ acao, sessao })
    if (acao === 'corrigir' && sessao.id) {
      // O PATCH não devolve `equipe_minima`; o GET diz se a fonte é a escala.
      void api.GET('/sessions/{id}', { params: { path: { id: sessao.id } }, headers: cabecalhos() })
        .then(({ data }) => {
          const origem = (data?.equipe_minima as { origem?: string } | undefined)?.origem
          setEquipeDaEscala(origem === 'escala')
        })
        .catch(() => setEquipeDaEscala(false))
    }
  }

  async function salvar(acao: Acao | 'abrir', sessao: Sessao, realizada?: boolean) {
    if (!podeGerenciar || !sessao.id) return
    if ((acao === 'cancelar' || acao === 'corrigir') && !motivoValido) return
    setErro(null)
    setEnviando(true)
    const options = { params: { path: { id: sessao.id } }, headers: cabecalhos() }
    try {
      let resposta
      switch (acao) {
        case 'abrir':
          resposta = await api.POST('/sessions/{id}/open', options)
          break
        case 'cancelar':
          resposta = await api.POST('/sessions/{id}/cancel', {
            ...options, body: { motivo: motivo.trim() },
          })
          break
        case 'encerrar':
          resposta = await api.POST('/sessions/{id}/close', {
            ...options, body: { realizada: realizada === true },
          })
          break
        case 'corrigir':
          resposta = await api.PATCH('/sessions/{id}', {
            ...options,
            body: {
              tema: tema.trim() || null, passagem_biblica: passagem.trim() || null,
              auxiliares_escalados: equipeDaEscala ? undefined : auxiliares === '' ? null : Number(auxiliares),
              motivo: motivo.trim(),
            },
          })
          break
      }
      if (resposta.error) throw resposta.error
      toast.success({
        abrir: 'Sessão aberta.', cancelar: 'Sessão cancelada.',
        encerrar: 'Sessão encerrada.', corrigir: 'Sessão corrigida.',
      }[acao])
      setDialogo(null)
      await carregar()
    } catch (error) {
      setErro(lerErro(error, 'Não foi possível atualizar a sessão.').detail)
    } finally {
      setEnviando(false)
    }
  }

  function enviarDialogo(evento: FormEvent) {
    evento.preventDefault()
    if (dialogo && dialogo.acao !== 'encerrar') void salvar(dialogo.acao, dialogo.sessao)
  }

  const grupos = new Map<string, Sessao[]>()
  for (const sessao of sessoes) {
    const dia = formatarData(sessao.inicio_previsto, sessao.timezone)
    const grupo = grupos.get(dia) ?? []
    grupo.push(sessao)
    grupos.set(dia, grupo)
  }

  return (
    <section className="mx-auto flex max-w-5xl flex-col gap-6">
      {erro && !dialogo && (
        <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>
      )}
      <Card>
        <CardHeader><CardTitle>Sessões</CardTitle></CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="flex flex-col gap-2">
              <Label htmlFor="sessoes-ebi">EBI</Label>
              <Select value={ebiId} onValueChange={setEbiId} disabled={enviando || Boolean(dialogo) || ebis.length === 0}>
                <SelectTrigger id="sessoes-ebi" className="w-full"><SelectValue placeholder="Selecione um EBI" /></SelectTrigger>
                <SelectContent>
                  {ebis.filter((ebi) => ebi.id).map((ebi) => (
                    <SelectItem key={ebi.id} value={ebi.id!}>{ebi.nome} — {ebi.igreja_nome}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="sessoes-de">De</Label>
              <Input id="sessoes-de" type="date" value={de} max={ate || undefined} disabled={enviando || Boolean(dialogo)} onChange={(e) => setDe(e.target.value)} required />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="sessoes-ate">Até</Label>
              <Input id="sessoes-ate" type="date" value={ate} min={de || undefined} disabled={enviando || Boolean(dialogo)} onChange={(e) => setAte(e.target.value)} required />
            </div>
          </div>
          {podeGerenciar && ebiId && periodoValido && !carregando && sessoes.length === 0 && (
            <Button type="button" className="self-start" onClick={() => void gerar()} disabled={enviando}>
              {enviando ? 'Gerando…' : 'Gerar próximas semanas'}
            </Button>
          )}
          <Table>
            <TableHeader><TableRow>
              <TableHead>Horário</TableHead><TableHead>Tema</TableHead><TableHead>Situação</TableHead><TableHead>Presentes</TableHead><TableHead>Ações</TableHead>
            </TableRow></TableHeader>
            <TableBody>
              <EstadoTabela colunas={5} carregando={carregando} vazio={sessoes.length === 0} mensagemVazio={ebiId ? 'Nenhuma sessão neste período.' : 'Nenhum EBI disponível.'} />
              {!carregando && [...grupos].map(([dia, sessoesDoDia]) => (
                <Fragment key={dia}>
                  <TableRow className="bg-muted/50"><TableCell colSpan={5} className="font-semibold">{dia}</TableCell></TableRow>
                  {sessoesDoDia.map((sessao) => (
                    <TableRow key={sessao.id}>
                      <TableCell>{formatarHora(sessao.inicio_previsto, sessao.timezone)} – {formatarHora(sessao.fim_previsto, sessao.timezone)}</TableCell>
                      <TableCell className="whitespace-normal">{sessao.tema || '—'}</TableCell>
                      <TableCell className="whitespace-normal">
                        {rotuloStatus(sessao.status)}
                        {sessao.status === 'cancelada' && <p className="text-xs text-muted-foreground">{sessao.motivo_cancelamento}</p>}
                      </TableCell>
                      <TableCell>{sessao.presentes ?? 0}</TableCell>
                      <TableCell><div className="flex flex-wrap gap-2">
                        {sessao.id && (sessao.status === 'aberta' || sessao.status === 'encerrada') && (
                          <Button type="button" variant="outline" size="sm" onClick={() => onAbrirPresenca(sessao.id!)} disabled={enviando}>Presença</Button>
                        )}
                        {podeGerenciar && sessao.id && sessao.status === 'prevista' && <>
                          <Button type="button" size="sm" onClick={() => void salvar('abrir', sessao)} disabled={enviando}>Abrir</Button>
                          <Button type="button" variant="outline" size="sm" onClick={() => abrirDialogo('cancelar', sessao)} disabled={enviando}>Cancelar</Button>
                        </>}
                        {podeGerenciar && sessao.id && sessao.status === 'aberta' && (
                          <Button type="button" variant="outline" size="sm" onClick={() => abrirDialogo('encerrar', sessao)} disabled={enviando}>Encerrar</Button>
                        )}
                        {podeGerenciar && sessao.id && sessao.status === 'encerrada' && (
                          <Button type="button" variant="outline" size="sm" onClick={() => abrirDialogo('corrigir', sessao)} disabled={enviando}>Corrigir</Button>
                        )}
                      </div></TableCell>
                    </TableRow>
                  ))}
                </Fragment>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
      {podeGerenciar && ebiId && <PainelAgenda key={ebiId} ebiId={ebiId} podeGerenciar={podeGerenciar} onAgendaAlterada={carregar} />}
      <Dialog open={Boolean(dialogo)} onOpenChange={(aberto) => { if (!aberto && !enviando) setDialogo(null) }}>
        <DialogContent showCloseButton={!enviando}>
          <DialogHeader>
            <DialogTitle>{dialogo ? { cancelar: 'Cancelar sessão', encerrar: 'Encerrar sessão', corrigir: 'Corrigir sessão' }[dialogo.acao] : 'Sessão'}</DialogTitle>
            <DialogDescription>
              {dialogo?.acao === 'encerrar' ? 'Informe se a sessão foi realizada.' : 'Informe os dados e o motivo obrigatório para registrar esta alteração.'}
            </DialogDescription>
          </DialogHeader>
          {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
          <form onSubmit={enviarDialogo} className="flex flex-col gap-4">
            {dialogo?.acao === 'corrigir' && <>
              <div className="flex flex-col gap-2"><Label htmlFor="sessao-tema">Tema</Label><Input id="sessao-tema" value={tema} onChange={(e) => setTema(e.target.value)} disabled={enviando} /></div>
              <div className="flex flex-col gap-2"><Label htmlFor="sessao-passagem">Passagem bíblica</Label><Input id="sessao-passagem" value={passagem} onChange={(e) => setPassagem(e.target.value)} disabled={enviando} /></div>
              <div className="flex flex-col gap-2"><Label htmlFor="sessao-auxiliares">Auxiliares escalados</Label>
                {equipeDaEscala === true ? (
                  <p className="text-xs text-muted-foreground">Definida pela escala aprovada.</p>
                ) : (
                  <Input id="sessao-auxiliares" type="number" min="0" step="1" value={auxiliares} onChange={(e) => setAuxiliares(e.target.value)} disabled={enviando} />
                )}
              </div>
            </>}
            {dialogo && dialogo.acao !== 'encerrar' && (
              <div className="flex flex-col gap-2"><Label htmlFor="sessao-motivo">Motivo (3 a 500 caracteres)</Label><Input id="sessao-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} minLength={3} maxLength={500} required disabled={enviando} /></div>
            )}
            <DialogFooter>
              <Button type="button" variant="outline" disabled={enviando} onClick={() => setDialogo(null)}>Voltar</Button>
              {dialogo?.acao === 'encerrar' ? <>
                <Button type="button" variant="outline" disabled={enviando} onClick={() => void salvar('encerrar', dialogo.sessao, false)}>Não realizada</Button>
                <Button type="button" disabled={enviando} onClick={() => void salvar('encerrar', dialogo.sessao, true)}>Realizada</Button>
              </> : <Button type="submit" disabled={enviando || !motivoValido}>{enviando ? 'Salvando…' : 'Confirmar'}</Button>}
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </section>
  )
}
