import { useCallback, useEffect, useRef, useState, type FormEvent } from 'react'
import { toast } from 'sonner'

import { api } from '../../api/client'
import { lerErro } from '../../api/erro'
import { cabecalhos } from '../../api/sessao'
import type { components } from '../../api/schema'
import { ConfirmarAcao } from '../ConfirmarAcao'
import { EstadoTabela } from '../EstadoTabela'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatarData, hojeIso, rotuloStatus } from '@/lib/formatos'

type Faixa = components['schemas']['AgendaEbi']
type Excecao = components['schemas']['AgendaExcecao']
type TipoExcecao = components['schemas']['AgendaExcecaoInput']['tipo']

const DIAS = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo']
const TIPOS: TipoExcecao[] = ['feriado', 'cancelamento', 'horario_especial']

function situacaoFaixa(faixa: Faixa) {
  const hoje = hojeIso()
  if (faixa.fim_vigencia && faixa.fim_vigencia < hoje) return 'encerrada'
  if (faixa.inicio_vigencia && faixa.inicio_vigencia > hoje) return 'futura'
  return 'vigente'
}

export function PainelAgenda({ ebiId, podeGerenciar, onAgendaAlterada }: {
  ebiId: string
  podeGerenciar: boolean
  onAgendaAlterada?: () => void
}) {
  const [faixas, setFaixas] = useState<Faixa[]>([])
  const [excecoes, setExcecoes] = useState<Excecao[]>([])
  const [carregando, setCarregando] = useState(true)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [dia, setDia] = useState('1')
  const [horaInicio, setHoraInicio] = useState('')
  const [horaFim, setHoraFim] = useState('')
  const [inicioVigencia, setInicioVigencia] = useState(hojeIso)
  const [fimVigencia, setFimVigencia] = useState('')
  const [tipo, setTipo] = useState<TipoExcecao>('feriado')
  const [dataExcecao, setDataExcecao] = useState('')
  const [faixaId, setFaixaId] = useState('')
  const [excecaoInicio, setExcecaoInicio] = useState('')
  const [excecaoFim, setExcecaoFim] = useState('')
  const [motivo, setMotivo] = useState('')
  const consulta = useRef<AbortController | null>(null)
  const aoAlterar = useRef(onAgendaAlterada)
  const horarioEspecial = tipo === 'horario_especial'

  useEffect(() => {
    aoAlterar.current = onAgendaAlterada
  }, [onAgendaAlterada])

  const carregar = useCallback(async (signal?: AbortSignal) => {
    setCarregando(true)
    setErro(null)
    try {
      const [agenda, exceptions] = await Promise.all([
        api.GET('/ebis/{id}/agenda', {
          params: { path: { id: ebiId } }, headers: cabecalhos(), signal,
        }),
        api.GET('/ebis/{id}/agenda-exceptions', {
          params: { path: { id: ebiId } }, headers: cabecalhos(), signal,
        }),
      ])
      if (signal?.aborted) return
      if (agenda.error || !agenda.data) throw agenda.error
      if (exceptions.error || !exceptions.data) throw exceptions.error
      setFaixas(Array.isArray(agenda.data) ? agenda.data : (agenda.data.data ?? []))
      setExcecoes(Array.isArray(exceptions.data) ? exceptions.data : (exceptions.data.data ?? []))
    } catch (error) {
      if (!signal?.aborted) {
        setErro(lerErro(error, 'Não foi possível carregar a agenda.').detail)
      }
    } finally {
      if (!signal?.aborted) setCarregando(false)
    }
  }, [ebiId])

  useEffect(() => {
    const controller = new AbortController()
    consulta.current = controller
    setFaixas([])
    setExcecoes([])
    setFaixaId('')
    setEnviando(false)
    void carregar(controller.signal)
    return () => controller.abort()
  }, [carregar])

  async function escrever(
    chamada: () => Promise<{ error?: unknown }>,
    mensagem: string,
    limpar?: () => void,
  ) {
    if (!podeGerenciar) return
    const signal = consulta.current?.signal
    setErro(null)
    setEnviando(true)
    try {
      const { error } = await chamada()
      if (signal?.aborted) return
      if (error) throw error
      toast.success(mensagem)
      limpar?.()
      await carregar(signal)
      if (!signal?.aborted) aoAlterar.current?.()
    } catch (error) {
      if (!signal?.aborted) {
        setErro(lerErro(error, 'Não foi possível alterar a agenda.').detail)
      }
    } finally {
      if (!signal?.aborted) setEnviando(false)
    }
  }

  function criarFaixa(evento: FormEvent) {
    evento.preventDefault()
    void escrever(() => api.POST('/ebis/{id}/agenda', {
      params: { path: { id: ebiId } }, headers: cabecalhos(),
      body: {
        dia_semana: Number(dia), hora_inicio: horaInicio, hora_fim: horaFim,
        inicio_vigencia: inicioVigencia, fim_vigencia: fimVigencia || null,
      },
    }), 'Faixa criada.', () => {
      setHoraInicio('')
      setHoraFim('')
      setFimVigencia('')
    })
  }

  function criarExcecao(evento: FormEvent) {
    evento.preventDefault()
    if (motivo.trim().length < 3 || (horarioEspecial && !faixaId)) return
    void escrever(() => api.POST('/ebis/{id}/agenda-exceptions', {
      params: { path: { id: ebiId } }, headers: cabecalhos(),
      body: {
        tipo, data: dataExcecao, motivo: motivo.trim(),
        agenda_ebi_id: horarioEspecial ? faixaId : null,
        hora_inicio: horarioEspecial ? excecaoInicio : null,
        hora_fim: horarioEspecial ? excecaoFim : null,
      },
    }), 'Exceção criada.', () => {
      setDataExcecao('')
      setFaixaId('')
      setExcecaoInicio('')
      setExcecaoFim('')
      setMotivo('')
    })
  }

  return (
    <Card>
      <CardHeader><CardTitle>Agenda</CardTitle></CardHeader>
      <CardContent className="flex flex-col gap-4">
        {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
        <Tabs defaultValue="faixas">
          <TabsList aria-label="Agenda"><TabsTrigger value="faixas">Faixas</TabsTrigger><TabsTrigger value="excecoes">Exceções</TabsTrigger></TabsList>
          <TabsContent value="faixas" className="flex flex-col gap-6">
            <Table>
              <TableHeader><TableRow>
                <TableHead>Dia da semana</TableHead><TableHead>Horário</TableHead><TableHead>Vigência</TableHead><TableHead>Situação</TableHead>{podeGerenciar && <TableHead>Ações</TableHead>}
              </TableRow></TableHeader>
              <TableBody>
                <EstadoTabela colunas={podeGerenciar ? 5 : 4} carregando={carregando} vazio={faixas.length === 0} mensagemVazio="Nenhuma faixa cadastrada." />
                {!carregando && faixas.map((faixa) => (
                  <TableRow key={faixa.id}>
                    <TableCell>{DIAS[faixa.dia_semana ?? 0] || '—'}</TableCell>
                    <TableCell>{faixa.hora_inicio} – {faixa.hora_fim}</TableCell>
                    <TableCell>{formatarData(faixa.inicio_vigencia)} – {faixa.fim_vigencia ? formatarData(faixa.fim_vigencia) : 'Sem fim'}</TableCell>
                    <TableCell>{situacaoFaixa(faixa)}</TableCell>
                    {podeGerenciar && <TableCell>
                      {faixa.id && situacaoFaixa(faixa) !== 'encerrada' && (
                        <ConfirmarAcao titulo="Encerrar esta faixa?" descricao="Esta faixa deixará de gerar novas sessões. As sessões já existentes serão preservadas." rotuloConfirmar="Encerrar faixa" onConfirmar={() => escrever(() => api.DELETE('/agenda/{id}', { params: { path: { id: faixa.id! } }, headers: cabecalhos() }), 'Faixa encerrada.')}>
                          <Button type="button" variant="outline" size="sm" disabled={enviando}>Encerrar</Button>
                        </ConfirmarAcao>
                      )}
                    </TableCell>}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {podeGerenciar && (
              <form onSubmit={criarFaixa} className="grid gap-4 sm:grid-cols-2">
                <h3 className="font-semibold sm:col-span-2">Nova faixa</h3>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="agenda-dia">Dia da semana</Label>
                  <Select value={dia} onValueChange={setDia} disabled={enviando}>
                    <SelectTrigger id="agenda-dia" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>{DIAS.slice(1).map((nome, indice) => <SelectItem key={nome} value={String(indice + 1)}>{nome}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div className="flex flex-col gap-2"><Label htmlFor="agenda-inicio">Hora de início</Label><Input id="agenda-inicio" type="time" value={horaInicio} onChange={(e) => setHoraInicio(e.target.value)} required disabled={enviando} /></div>
                <div className="flex flex-col gap-2"><Label htmlFor="agenda-fim">Hora de fim</Label><Input id="agenda-fim" type="time" value={horaFim} onChange={(e) => setHoraFim(e.target.value)} required disabled={enviando} /></div>
                <div className="flex flex-col gap-2"><Label htmlFor="agenda-vigencia-inicio">Início da vigência</Label><Input id="agenda-vigencia-inicio" type="date" value={inicioVigencia} max={fimVigencia || undefined} onChange={(e) => setInicioVigencia(e.target.value)} required disabled={enviando} /></div>
                <div className="flex flex-col gap-2"><Label htmlFor="agenda-vigencia-fim">Fim da vigência (opcional)</Label><Input id="agenda-vigencia-fim" type="date" value={fimVigencia} min={inicioVigencia || undefined} onChange={(e) => setFimVigencia(e.target.value)} disabled={enviando} /></div>
                <Button type="submit" className="sm:col-span-2 sm:justify-self-start" disabled={enviando || carregando || !horaInicio || !horaFim || !inicioVigencia}>Criar faixa</Button>
              </form>
            )}
          </TabsContent>
          <TabsContent value="excecoes" className="flex flex-col gap-6">
            <Table>
              <TableHeader><TableRow>
                <TableHead>Data</TableHead><TableHead>Tipo</TableHead><TableHead>Horário</TableHead><TableHead>Motivo</TableHead>{podeGerenciar && <TableHead>Ações</TableHead>}
              </TableRow></TableHeader>
              <TableBody>
                <EstadoTabela colunas={podeGerenciar ? 5 : 4} carregando={carregando} vazio={excecoes.length === 0} mensagemVazio="Nenhuma exceção cadastrada." />
                {!carregando && excecoes.map((excecao) => (
                  <TableRow key={excecao.id}>
                    <TableCell>{formatarData(excecao.data)}</TableCell><TableCell>{rotuloStatus(excecao.tipo)}</TableCell>
                    <TableCell>{excecao.hora_inicio ? `${excecao.hora_inicio} – ${excecao.hora_fim}` : 'dia inteiro'}</TableCell>
                    <TableCell className="whitespace-normal">{excecao.motivo}</TableCell>
                    {podeGerenciar && <TableCell>{excecao.id && (
                      <ConfirmarAcao titulo="Remover esta exceção?" descricao="A exceção deixará de ser aplicada na geração de sessões." rotuloConfirmar="Remover exceção" onConfirmar={() => escrever(() => api.DELETE('/agenda-exceptions/{id}', { params: { path: { id: excecao.id! } }, headers: cabecalhos() }), 'Exceção removida.')}>
                        <Button type="button" variant="outline" size="sm" disabled={enviando}>Remover</Button>
                      </ConfirmarAcao>
                    )}</TableCell>}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {podeGerenciar && (
              <form onSubmit={criarExcecao} className="grid gap-4 sm:grid-cols-2">
                <h3 className="font-semibold sm:col-span-2">Nova exceção</h3>
                <div className="flex flex-col gap-2">
                  <Label htmlFor="excecao-tipo">Tipo</Label>
                  <Select value={tipo} onValueChange={(valor) => setTipo(valor as TipoExcecao)} disabled={enviando}>
                    <SelectTrigger id="excecao-tipo" className="w-full"><SelectValue /></SelectTrigger>
                    <SelectContent>{TIPOS.map((valor) => <SelectItem key={valor} value={valor}>{rotuloStatus(valor)}</SelectItem>)}</SelectContent>
                  </Select>
                </div>
                <div className="flex flex-col gap-2"><Label htmlFor="excecao-data">Data</Label><Input id="excecao-data" type="date" value={dataExcecao} onChange={(e) => setDataExcecao(e.target.value)} required disabled={enviando} /></div>
                {horarioEspecial && <>
                  <div className="flex flex-col gap-2 sm:col-span-2">
                    <Label htmlFor="excecao-faixa">Faixa (obrigatória para horário especial)</Label>
                    <Select value={faixaId} onValueChange={setFaixaId} disabled={enviando} required>
                      <SelectTrigger id="excecao-faixa" className="w-full" aria-required="true"><SelectValue placeholder="Selecione uma faixa" /></SelectTrigger>
                      <SelectContent>{faixas.filter((faixa) => faixa.id).map((faixa) => (
                        <SelectItem key={faixa.id} value={faixa.id!}>{DIAS[faixa.dia_semana ?? 0]} · {faixa.hora_inicio} – {faixa.hora_fim} · {formatarData(faixa.inicio_vigencia)}</SelectItem>
                      ))}</SelectContent>
                    </Select>
                  </div>
                  <div className="flex flex-col gap-2"><Label htmlFor="excecao-inicio">Hora de início</Label><Input id="excecao-inicio" type="time" value={excecaoInicio} onChange={(e) => setExcecaoInicio(e.target.value)} required disabled={enviando} /></div>
                  <div className="flex flex-col gap-2"><Label htmlFor="excecao-fim">Hora de fim</Label><Input id="excecao-fim" type="time" value={excecaoFim} onChange={(e) => setExcecaoFim(e.target.value)} required disabled={enviando} /></div>
                </>}
                <div className="flex flex-col gap-2 sm:col-span-2"><Label htmlFor="excecao-motivo">Motivo (obrigatório, 3 a 247 caracteres)</Label><Input id="excecao-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} minLength={3} maxLength={247} required disabled={enviando} /></div>
                <Button type="submit" className="sm:col-span-2 sm:justify-self-start" disabled={enviando || carregando || !dataExcecao || motivo.trim().length < 3 || (horarioEspecial && (!faixaId || !excecaoInicio || !excecaoFim))}>Criar exceção</Button>
              </form>
            )}
          </TabsContent>
        </Tabs>
      </CardContent>
    </Card>
  )
}
