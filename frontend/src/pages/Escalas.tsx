import { useEffect, useState } from 'react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import type { components } from '../api/schema'
import { cabecalhos, type ContextoAtuacao } from '../api/sessao'
import {
  abrirVaga,
  alterarEstimativa,
  aprovarEscala,
  cancelarVaga,
  criarEscala,
  decidirTroca,
  equipeDoEbi,
  type EquipeResumo,
  escolherOferta,
  incluirMembro,
  listarEquipes,
  listarEscalas,
  listarTrocas,
  listarVagasEbi,
  obterEquipe,
  obterEscala,
  retirarMembro,
  type MembroDaEquipe,
  type VagaEscala,
} from '../api/escalas'
import { EstadoTabela } from '../components/EstadoTabela'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import { formatarData, formatarHora, hojeIso, rotuloSituacaoEscala, rotuloSituacaoMembro, rotuloStatusOferta, rotuloStatusVaga, rotuloTipoVaga } from '@/lib/formatos'

type Escala = components['schemas']['Escala']
type EscalaResumo = components['schemas']['EscalaResumo']
type TrocaEscala = components['schemas']['TrocaEscala']

/** Situação da sessão na lista, em palavra que a tela mostra. */
const SITUACOES: Record<string, string> = {
  sem_escala: 'Sem escala',
  cancelada: 'Sessão cancelada',
  abaixo_do_minimo: 'Abaixo do mínimo',
  aguardando_aprovacao: 'Aguardando aprovação',
  aprovada: 'Aprovada',
}

const SITUACOES_TROCA: Record<string, string> = {
  pendente: 'Pendente',
  aprovada: 'Aprovada',
  recusada: 'Recusada',
  cancelada: 'Cancelada',
  expirada: 'Expirada',
}

function daquiTrintaDias() {
  const data = new Date()
  data.setDate(data.getDate() + 30)
  return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`
}

/** Reduz a equipe mínima sem depender do formato livre do contrato. */
function textoEquipeMinima(escala: Escala | undefined): string {
  const minima = escala?.equipe_minima as Record<string, unknown> | undefined
  if (!minima) return '—'
  const coordenadoras = Number(minima.coordenadoras_escaladas ?? 0)
  const necessarias = Number(minima.coordenadoras_necessarias ?? 0)
  const auxiliares = Number(minima.auxiliares_escalados ?? 0)
  const necessarios = Number(minima.auxiliares_necessarios ?? 0)
  return `${coordenadoras}/${necessarias} coord. · ${auxiliares}/${necessarios} aux.`
}

/**
 * Escalas: por EBI e período, a situação da escala de cada sessão; no detalhe,
 * a estimativa, os membros com conflitos (CA-12) e a equipe mínima; "Aprovar"
 * apenas para quem aprova de fato (a chefe, ou o `adm_igreja` sem chefe); e as
 * trocas pendentes com a escolha do substituto.
 *
 * Montar parte da equipe da coordenadora: a dona entra como coordenadora e os
 * demais membros vigentes como auxiliares — a escala nasce inteira, e a equipe
 * fica registrada como dona dela.
 */
export function Escalas({
  contexto,
  papeis,
  usuario,
}: {
  contexto: ContextoAtuacao | null
  papeis: string[]
  usuario: { id?: string } | null
}) {
  const [ebis, setEbis] = useState<components['schemas']['EbiResumo'][]>([])
  const [ebiId, setEbiId] = useState('')
  const [inicio, setInicio] = useState(hojeIso)
  const [fim, setFim] = useState(daquiTrintaDias)
  const [resumos, setResumos] = useState<EscalaResumo[]>([])
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [sessaoAberta, setSessaoAberta] = useState<string | null>(null)
  const [escala, setEscala] = useState<Escala | null>(null)
  const [equipe, setEquipe] = useState<MembroDaEquipe[]>([])
  const [novaEstimativa, setNovaEstimativa] = useState('')
  const [novoMembro, setNovoMembro] = useState('')
  const novaFuncao = 'auxiliar' as const
  const [trocas, setTrocas] = useState<TrocaEscala[]>([])
  const [substituto, setSubstituto] = useState<Record<string, string>>({})
  const [vagasDoEbi, setVagasDoEbi] = useState<VagaEscala[]>([])
  const [abrindoVaga, setAbrindoVaga] = useState(false)
  const [vagaObservacao, setVagaObservacao] = useState('')

  const podeAprovar = papeis.some((p) => ['coordenadora_chefe', 'adm_igreja'].includes(p))
  const podeGerir = papeis.some((p) => ['coordenadora', 'coordenadora_chefe', 'adm_igreja'].includes(p))
  // A leitura sem EBI explícito no contexto usa o primeiro EBI do usuário.
  const ebiDoContexto = contexto?.tipo_escopo === 'ebi' ? contexto.escopo_id : ''

  useEffect(() => {
    let vivo = true
    async function carregarEbis() {
      const { data } = await api.GET('/me/ebis', { headers: cabecalhos() })
      if (!vivo) return
      const lista = data?.data ?? []
      setEbis(lista)
      // O seletor começa no EBI do contexto (ou no primeiro da lista) e o
      // carregamento segue a partir da escolha.
      const inicial = ebiDoContexto || lista[0]?.id || ''
      if (inicial) setEbiId(inicial)
      if (lista.length === 0) setCarregando(false)
    }
    void carregarEbis()
    return () => { vivo = false }
  }, [ebiDoContexto])

  async function carregarEscalas(mostrarCarregando = false) {
    if (!ebiId) return
    if (mostrarCarregando) setCarregando(true)
    setErro(null)
    const { data, error } = await listarEscalas(ebiId, inicio, fim)
    if (error) setErro(lerErro(error, 'Não foi possível carregar as escalas.').detail)
    setResumos(data ?? [])
    setCarregando(false)
  }

  // A tela já abre carregando: o primeiro pedido não marca o estado no efeito.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void carregarEscalas() }, [ebiId, inicio, fim]) // eslint-disable-line react-hooks/exhaustive-deps

  async function carregarTrocas() {
    if (!ebiId || !podeAprovar) return
    const { data } = await listarTrocas(ebiId)
    setTrocas((data ?? []).filter((t) => t.situacao === 'pendente'))
  }

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void carregarTrocas() }, [ebiId, podeAprovar]) // eslint-disable-line react-hooks/exhaustive-deps

  /** Painel do EBI: todas as vagas abertas, a mais próxima primeiro. */
  async function carregarVagas() {
    if (!ebiId) return
    const { data } = await listarVagasEbi(ebiId, { status: 'aberta', inicio: hojeIso(), fim })
    setVagasDoEbi(data ?? [])
  }

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void carregarVagas() }, [ebiId, fim]) // eslint-disable-line react-hooks/exhaustive-deps

  async function abrir(sessaoId: string) {
    setErro(null)
    setSessaoAberta(sessaoId)
    setEscala(null)
    const resumo = resumos.find((r) => r.sessao_id === sessaoId)
    // Sem escala ainda: a lista sabe a situação; o painel vira criação.
    if (!resumo?.escala_id) {
      setNovaEstimativa('')
      await carregarEquipe()
      return
    }
    const { data, error } = await obterEscala(resumo.escala_id)
    if (error) {
      setErro(lerErro(error, 'Não foi possível carregar a escala.').detail)
      return
    }
    setEscala(data ?? null)
    setNovaEstimativa(String(data?.estimativa_participantes ?? ''))
    await carregarEquipe()
  }

  async function carregarEquipe() {
    if (!ebiId) return
    const { data } = await equipeDoEbi(ebiId)
    setEquipe(data ?? [])
  }

  /**
   * Equipe da coordenadora que monta a escala: `null` quando não há nenhuma
   * (a própria ou, para chefe e `adm_igreja`, a de qualquer coordenadora
   * vigente). Quem monta a escala é a dona da equipe escolhida.
   */
  async function equipeParaMontar(): Promise<EquipeResumo | null> {
    const { data, error } = await listarEquipes(ebiId)
    if (error) {
      setErro(lerErro(error, 'Não foi possível carregar as equipes.').detail)
      return null
    }
    const todas = data ?? []
    const minhaId = usuario?.id ?? ''
    // Para a chefe, `minha` é a dela; sem a dela, vale a primeira de uma
    // coordenadora vigente (é ela quem escolhe, e o servidor confere).
    return todas.find((e) => e.coordenadora_id === minhaId) ?? todas[0] ?? null
  }

  /** Dona como coordenadora e os demais membros vigentes como auxiliares. */
  async function membrosDaEquipe(equipeDona: EquipeResumo) {
    const { data } = await obterEquipe(equipeDona.id ?? '')
    const membros = data?.membros ?? []
    const linhas: { usuario_id: string; funcao: 'coordenadora' | 'auxiliar' }[] = []

    if (data?.coordenadora_id) {
      linhas.push({ usuario_id: data.coordenadora_id, funcao: 'coordenadora' })
    }
    for (const membro of membros) {
      if (membro.usuario_id && !membro.sem_vinculo) {
        linhas.push({ usuario_id: membro.usuario_id, funcao: 'auxiliar' })
      }
    }

    return linhas
  }

  async function executar<T>(acao: () => Promise<{ data?: T; error?: unknown }>, sucesso: string) {
    setEnviando(true)
    setErro(null)
    const { data, error } = await acao()
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível concluir a ação.').detail)
      return null
    }
    toast.success(sucesso)
    return data ?? null
  }

  async function criar() {
    if (!sessaoAberta || enviando) return
    setEnviando(true)
    setErro(null)

    const equipeDona = await equipeParaMontar()

    if (!equipeDona) {
      setEnviando(false)
      setErro('Crie a equipe da coordenadora em “Equipes de escala” antes de montar a escala.')
      return
    }

    const membros = await membrosDaEquipe(equipeDona)
    setEnviando(false)

    const dados = await executar(
      () => criarEscala(sessaoAberta, Number(novaEstimativa || 0), membros, equipeDona.id ?? ''),
      'Escala criada.',
    )
    if (dados) {
      setEscala(dados)
      setNovaEstimativa(String(dados.estimativa_participantes ?? ''))
      await carregarEscalas()
    }
  }

  async function salvarEstimativa() {
    if (!escala?.id) return
    const dados = await executar(
      () => alterarEstimativa(escala.id!, Number(novaEstimativa || 0)),
      'Estimativa alterada; a escala voltou a prevista.',
    )
    if (dados) {
      setEscala(dados)
      await carregarEscalas()
    }
  }

  async function incluir() {
    if (!escala?.id || !novoMembro) return
    const dados = await executar(
      () => incluirMembro(escala.id!, novoMembro, novaFuncao),
      'Membro incluído.',
    )
    if (dados) {
      setEscala(dados)
      setNovoMembro('')
      await carregarEscalas()
    }
  }

  async function retirar(membroId: string) {
    const dados = await executar(() => retirarMembro(membroId), 'Membro retirado.')
    if (dados) {
      setEscala(dados)
      await carregarEscalas()
    }
  }

  async function aprovar() {
    if (!escala?.id) return
    const dados = await executar(() => aprovarEscala(escala.id!), 'Escala aprovada.')
    if (dados) {
      setEscala(dados)
      await carregarEscalas()
    }
  }

  async function abrirVagaNaEscala() {
    if (!escala?.id) return
    const dados = await executar(
      () => abrirVaga(escala.id!, { observacao: vagaObservacao || null }),
      'Vaga aberta; os auxiliares do EBI podem se oferecer.',
    )
    if (dados) {
      setAbrindoVaga(false)
      setVagaObservacao('')
    }
    // Mesmo no erro, o cartão pode ter mudado (vaga já aberta por outra pessoa).
    await Promise.all([abrir(escala.sessao_id!), carregarVagas()])
  }

  async function cancelarVagaDaEscala(vagaId: string) {
    await executar(() => cancelarVaga(vagaId), 'Vaga cancelada.')
    await Promise.all([abrir(escala!.sessao_id!), carregarVagas()])
  }

  /** A dona escolhe; a pessoa entra já confirmada e a vaga fecha. */
  async function escolher(vagaId: string, ofertaId: string) {
    await executar(() => escolherOferta(vagaId, ofertaId), 'Oferta aceita.')
    await Promise.all([abrir(escala!.sessao_id!), carregarVagas(), carregarEscalas()])
  }

  async function decidir(troca: TrocaEscala, decisao: 'aprovar' | 'recusar') {
    if (!troca.id) return
    const escolhido = substituto[troca.id!]
    if (decisao === 'aprovar' && !escolhido) {
      setErro('Escolha o substituto para aprovar a troca.')
      return
    }
    const dados = await executar(
      () => decidirTroca(troca.id!, decisao, escolhido),
      decisao === 'aprovar' ? 'Troca aprovada.' : 'Troca recusada.',
    )
    if (dados !== null) {
      // A vaga teve a situação alterada: recarrega o detalhe aberto, se for da sessão.
      const vaga = vagaDaTroca(troca)
      const daSessao = (escala?.membros ?? []).some((m) => m.id === vaga)
      if (daSessao) await abrir(escala!.sessao_id!)
      await Promise.all([carregarTrocas(), carregarEscalas()])
    }
  }

  /** Id da vaga que a troca pede para substituir, quando o contrato o traz. */
  function vagaDaTroca(troca: TrocaEscala): string | undefined {
    const registro = troca as unknown as { escala_membro_id?: string; membro_id?: string }
    return registro.escala_membro_id ?? registro.membro_id
  }

  const resumoDaSessao = resumos.find((r) => r.sessao_id === sessaoAberta)

  return (
    <section className="mx-auto flex max-w-5xl flex-col gap-6">
      {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
      <Card>
        <CardHeader><CardTitle>Escalas</CardTitle><CardDescription>Equipe de cada sessão e a cobertura do período.</CardDescription></CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="flex flex-col gap-2">
              <Label htmlFor="escalas-ebi">EBI</Label>
              <Select value={ebiId} onValueChange={setEbiId} disabled={ebis.length === 0 || enviando}>
                <SelectTrigger id="escalas-ebi" className="w-full"><SelectValue placeholder="Selecione um EBI" /></SelectTrigger>
                <SelectContent>
                  {ebis.filter((ebi) => ebi.id).map((ebi) => (
                    <SelectItem key={ebi.id} value={ebi.id!}>{ebi.nome} — {ebi.igreja_nome}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="escalas-inicio">De</Label>
              <Input id="escalas-inicio" type="date" value={inicio} max={fim || undefined} onChange={(e) => setInicio(e.target.value)} />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="escalas-fim">Até</Label>
              <Input id="escalas-fim" type="date" value={fim} min={inicio || undefined} onChange={(e) => setFim(e.target.value)} />
            </div>
          </div>
          <Table>
            <TableHeader><TableRow>
              <TableHead>Sessão</TableHead><TableHead>Situação</TableHead><TableHead>Equipe</TableHead><TableHead>Ações</TableHead>
            </TableRow></TableHeader>
            <TableBody>
              <EstadoTabela colunas={4} carregando={carregando} vazio={resumos.length === 0} mensagemVazio="Nenhuma sessão neste período." />
              {resumos.map((r) => (
                <TableRow key={r.sessao_id}>
                  <TableCell className="whitespace-normal">
                    {formatarData(r.inicio_previsto)} {formatarHora(r.inicio_previsto)} – {formatarHora(r.fim_previsto)}
                  </TableCell>
                  <TableCell className="whitespace-normal">{SITUACOES[r.situacao ?? ''] ?? r.situacao ?? '—'}</TableCell>
                  <TableCell>{r.membros ?? 0} ({r.confirmados ?? 0} confirmados)</TableCell>
                  <TableCell>
                    <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => void abrir(r.sessao_id!)}>
                      {r.escala_id ? 'Detalhe' : 'Montar'}
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {sessaoAberta && (
        <Card>
          <CardHeader>
            <CardTitle>Escala da sessão</CardTitle>
            <CardDescription>
              {resumoDaSessao ? `${formatarData(resumoDaSessao.inicio_previsto)} — ${SITUACOES[resumoDaSessao.situacao ?? ''] ?? ''}` : 'Detalhe'}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {!escala && podeGerir && (
              <div className="flex flex-wrap items-end gap-3">
                <div className="flex flex-col gap-2">
                  <Label htmlFor="escala-estimativa">Estimativa de participantes</Label>
                  <Input id="escala-estimativa" type="number" min="0" step="1" value={novaEstimativa} onChange={(e) => setNovaEstimativa(e.target.value)} />
                </div>
                <Button type="button" disabled={enviando || novaEstimativa === ''} onClick={() => void criar()}>Criar escala</Button>
              </div>
            )}
            {escala && (
              <>
                <div className="flex flex-wrap items-center gap-3 text-sm">
                  <Badge variant={escala.status === 'aprovada' ? 'secondary' : 'outline'}>{rotuloSituacaoEscala(escala.status)}</Badge>
                  <span>Equipe mínima: {textoEquipeMinima(escala)}</span>
                  <span className="text-muted-foreground">
                    {(escala.equipe_minima as Record<string, unknown> | undefined)?.atingida ? 'Atingida' : 'Abaixo do mínimo'}
                  </span>
                </div>
                {podeGerir && (
                  <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-2">
                      <Label htmlFor="escala-nova-estimativa">Estimativa</Label>
                      <Input id="escala-nova-estimativa" type="number" min="0" step="1" value={novaEstimativa} onChange={(e) => setNovaEstimativa(e.target.value)} />
                    </div>
                    <Button type="button" variant="outline" disabled={enviando || novaEstimativa === ''} onClick={() => void salvarEstimativa()}>Salvar estimativa</Button>
                    {podeAprovar && escala.status === 'prevista' && (
                      <Button type="button" disabled={enviando} onClick={() => void aprovar()}>Aprovar</Button>
                    )}
                  </div>
                )}
                <Table>
                  <TableHeader><TableRow>
                    <TableHead>Pessoa</TableHead><TableHead>Função</TableHead><TableHead>Situação</TableHead><TableHead>Conflitos</TableHead><TableHead>Ações</TableHead>
                  </TableRow></TableHeader>
                  <TableBody>
                    {(escala.membros ?? []).map((membro) => (
                      <TableRow key={membro.id}>
                        <TableCell className="whitespace-normal">{membro.nome ?? '—'}</TableCell>
                        <TableCell>{membro.funcao === 'coordenadora' ? 'Coordenação' : 'Auxiliar'}</TableCell>
                        <TableCell>{rotuloSituacaoMembro(membro.status)}</TableCell>
                        <TableCell className="whitespace-normal">
                          {(membro.conflitos ?? []).length === 0 ? '—' : (membro.conflitos ?? []).map((c) => (
                            <p key={`${c.inicio}-${c.fim}`} className="text-xs text-muted-foreground">
                              {formatarData(c.inicio)} {formatarHora(c.inicio)} – {formatarHora(c.fim)}
                              {c.sessao_id === null && ' (outra igreja)'}
                              {c.sessao_id !== null && c.ebi_nome && ` (${c.ebi_nome})`}
                            </p>
                          ))}
                        </TableCell>
                        <TableCell>
                          {podeGerir && membro.status !== 'substituido' && membro.id && (
                            <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => void retirar(membro.id!)}>Retirar</Button>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                {podeGerir && (
                  <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-2">
                      <Label htmlFor="escala-membro">Incluir da equipe do EBI</Label>
                      <Select value={novoMembro} onValueChange={setNovoMembro}>
                        <SelectTrigger id="escala-membro" className="w-full"><SelectValue placeholder="Escolha a pessoa" /></SelectTrigger>
                        <SelectContent>
                          {equipe.map((pessoa) => (
                            <SelectItem key={pessoa.usuario_id} value={pessoa.usuario_id}>{pessoa.nome}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <Button type="button" variant="outline" disabled={enviando || !novoMembro} onClick={() => void incluir()}>Incluir</Button>
                  </div>
                )}

                {podeGerir && (
                  <div className="flex flex-col gap-3 rounded-md border p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="text-sm font-medium">Vagas desta escala</p>
                      {!abrindoVaga && (
                        <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => setAbrindoVaga(true)}>
                          Abrir vaga
                        </Button>
                      )}
                    </div>

                    {abrindoVaga && (
                      <div className="flex flex-col gap-2">
                        <Alert>
                          <AlertDescription>
                            A observação fica <strong>visível a todos os auxiliares do EBI</strong>. Não coloque dados de saúde nem
                            outros dados sensíveis.
                          </AlertDescription>
                        </Alert>
                        <div className="flex flex-col gap-2">
                          <Label htmlFor="vaga-observacao">Observação (opcional)</Label>
                          <Input
                            id="vaga-observacao"
                            value={vagaObservacao}
                            maxLength={280}
                            onChange={(e) => setVagaObservacao(e.target.value)}
                          />
                        </div>
                        <div className="flex flex-wrap gap-2">
                          <Button type="button" size="sm" disabled={enviando} onClick={() => void abrirVagaNaEscala()}>
                            Confirmar vaga
                          </Button>
                          <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => setAbrindoVaga(false)}>
                            Cancelar
                          </Button>
                        </div>
                      </div>
                    )}

                    {(escala.vagas ?? []).length === 0 ? (
                      <p className="text-sm text-muted-foreground">Nenhuma vaga nesta escala.</p>
                    ) : (
                      (escala.vagas ?? []).map((vaga) => (
                        <div key={vaga.id} className="flex flex-col gap-2 rounded-md border p-2">
                          <div className="flex flex-wrap items-center gap-2 text-sm">
                            <Badge variant="outline">{rotuloTipoVaga(vaga.tipo)}</Badge>
                            <Badge variant={vaga.status === 'aberta' ? 'default' : 'secondary'}>{rotuloStatusVaga(vaga.status)}</Badge>
                            {vaga.substituido_nome && <span>substitui {vaga.substituido_nome}</span>}
                            {vaga.escolhido_nome && <span>· escolhida: {vaga.escolhido_nome}</span>}
                            {vaga.status === 'aberta' && (
                              <span className="text-muted-foreground">
                                {vaga.ofertas_pendentes ?? 0} {(vaga.ofertas_pendentes ?? 0) === 1 ? 'oferta' : 'ofertas'}
                              </span>
                            )}
                          </div>
                          {vaga.observacao && <p className="text-sm text-muted-foreground">{vaga.observacao}</p>}
                          {vaga.motivo_encerramento && (
                            <p className="text-xs text-muted-foreground">Encerrada: {vaga.motivo_encerramento}</p>
                          )}
                          {vaga.status === 'aberta' && (
                            <Button
                              type="button"
                              variant="ghost"
                              size="sm"
                              className="self-start"
                              disabled={enviando}
                              onClick={() => void cancelarVagaDaEscala(vaga.id!)}
                            >
                              Cancelar vaga
                            </Button>
                          )}
                          {(vaga.ofertas ?? []).length > 0 && (
                            <ul className="flex flex-col gap-1">
                              {(vaga.ofertas ?? []).map((oferta) => (
                                <li key={oferta.id} className="flex flex-wrap items-center gap-2 text-sm">
                                  <span>{oferta.nome}</span>
                                  <span className="text-xs text-muted-foreground">{rotuloStatusOferta(oferta.status)}</span>
                                  {(oferta.conflitos ?? []).length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                      {oferta.conflitos?.length} conflito(s) de horário
                                    </span>
                                  )}
                                  {vaga.status === 'aberta' && oferta.status === 'pendente' && escala.pode_escolher_oferta && (
                                    <Button
                                      type="button"
                                      size="sm"
                                      disabled={enviando}
                                      onClick={() => void escolher(vaga.id!, oferta.id!)}
                                    >
                                      Escolher
                                    </Button>
                                  )}
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      ))
                    )}
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      )}

      {podeGerir && (
        <Card>
          <CardHeader>
            <CardTitle>Vagas do EBI</CardTitle>
            <CardDescription>
              Vagas abertas nas escalas deste EBI, a mais próxima primeiro. Quem se ofereceu aparece só na tela da
              coordenadora dona da escala — aqui, apenas a contagem.
            </CardDescription>
          </CardHeader>
          <CardContent>
            {vagasDoEbi.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nenhuma vaga aberta no período.</p>
            ) : (
              <ul className="flex flex-col gap-2">
                {vagasDoEbi.map((vaga) => (
                  <li key={vaga.id} className="flex flex-wrap items-center gap-2 rounded-md border p-2 text-sm">
                    <span className="font-medium">
                      {formatarData(vaga.sessao?.inicio_previsto)} {formatarHora(vaga.sessao?.inicio_previsto)} – {formatarHora(vaga.sessao?.fim_previsto)}
                    </span>
                    <Badge variant="outline">{rotuloTipoVaga(vaga.tipo)}</Badge>
                    {vaga.dona_nome && <span className="text-muted-foreground">· {vaga.dona_nome}</span>}
                    <span className="text-muted-foreground">
                      · {vaga.ofertas_pendentes ?? 0} {(vaga.ofertas_pendentes ?? 0) === 1 ? 'oferta' : 'ofertas'}
                    </span>
                    {vaga.escolhido_nome && <span>· escolhida: {vaga.escolhido_nome}</span>}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      )}

      {podeAprovar && (
        <Card>
          <CardHeader><CardTitle>Trocas pendentes</CardTitle></CardHeader>
          <CardContent className="flex flex-col gap-4">
            {trocas.length === 0 && <p className="text-sm text-muted-foreground">Nenhuma troca pendente.</p>}
            {trocas.map((troca) => (
              <div key={troca.id} className="flex flex-col gap-2 rounded-md border p-3">
                <p className="text-sm">
                  {troca.solicitante_nome ?? 'Solicitante'} pediu troca
                  {troca.motivo ? `: ${troca.motivo}` : '.'}
                  {troca.situacao === 'expirada' && ' (expirada)'}
                </p>
                <div className="flex flex-wrap items-end gap-3">
                  <div className="flex flex-col gap-2">
                    <Label htmlFor={`substituto-${troca.id}`}>Substituto</Label>
                    <Select value={substituto[troca.id ?? ''] ?? ''} onValueChange={(v) => setSubstituto({ ...substituto, [troca.id!]: v })}>
                      <SelectTrigger id={`substituto-${troca.id}`} className="w-full"><SelectValue placeholder="Escolha a pessoa" /></SelectTrigger>
                      <SelectContent>
                        {equipe.map((pessoa) => (
                          <SelectItem key={pessoa.usuario_id} value={pessoa.usuario_id}>{pessoa.nome}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <Button type="button" size="sm" disabled={enviando} onClick={() => void decidir(troca, 'aprovar')}>Aprovar troca</Button>
                  <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => void decidir(troca, 'recusar')}>Recusar</Button>
                </div>
                <p className="text-xs text-muted-foreground">{SITUACOES_TROCA[troca.situacao ?? ''] ?? troca.situacao}</p>
              </div>
            ))}
          </CardContent>
        </Card>
      )}
    </section>
  )
}
