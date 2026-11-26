import { useEffect, useState } from 'react'
import { toast } from 'sonner'

import { lerErro } from '../api/erro'
import type { components } from '../api/schema'
import {
  confirmarVaga,
  minhasEscalas,
  minhasVagas,
  oferecer,
  pedirTroca,
  retirarOferta,
  type VagaEscala,
} from '../api/escalas'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { formatarData, formatarHora, hojeIso, rotuloSituacaoMembro, rotuloTipoVaga } from '@/lib/formatos'

type MinhaEscala = components['schemas']['MinhaEscala']

/** Presença da vaga depois da sessão, em palavra que a tela mostra. */
function textoPresenca(presente: boolean | null | undefined): string {
  if (presente === true) return 'Esteve'
  if (presente === false) return 'Não esteve'
  return 'Não registrado'
}

function daquiNoventaDias() {
  const data = new Date()
  data.setDate(data.getDate() + 90)
  return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}-${String(data.getDate()).padStart(2, '0')}`
}

/**
 * Minhas escalas: as vagas da própria pessoa. Nas próximas, o conflito (CA-12)
 * vem antes do botão e a confirmação só existe depois da aprovação da escala;
 * nas passadas, "esteve / não esteve / não registrado".
 */
export function MinhasEscalas() {
  const [inicio, setInicio] = useState(hojeIso)
  const [fim, setFim] = useState(daquiNoventaDias)
  const [linhas, setLinhas] = useState<MinhaEscala[]>([])
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [motivo, setMotivo] = useState('')
  const [pedindoTroca, setPedindoTroca] = useState<string | null>(null)
  const [vagas, setVagas] = useState<VagaEscala[]>([])

  async function carregar(mostrarCarregando = false) {
    if (mostrarCarregando) setCarregando(true)
    setErro(null)
    const [{ data, error }, abertas] = await Promise.all([minhasEscalas(inicio, fim), minhasVagas(inicio, fim)])
    if (error) setErro(lerErro(error, 'Não foi possível carregar suas escalas.').detail)
    setLinhas(data ?? [])
    setVagas(abertas.data ?? [])
    setCarregando(false)
  }

  // A tela já abre carregando; a troca de período mostra o estado de novo.
  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void carregar() }, [inicio, fim]) // eslint-disable-line react-hooks/exhaustive-deps

  async function confirmar(membroId: string) {
    setEnviando(true)
    setErro(null)
    const { error } = await confirmarVaga(membroId)
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível confirmar.').detail)
      return
    }
    toast.success('Presença confirmada.')
    await carregar()
  }

  async function solicitarTroca(membroId: string) {
    setEnviando(true)
    setErro(null)
    const { error } = await pedirTroca(membroId, motivo)
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível pedir a troca.').detail)
      return
    }
    toast.success('Pedido de troca registrado; aguardando aprovação.')
    setPedindoTroca(null)
    setMotivo('')
    await carregar()
  }

  const hoje = hojeIso()

  async function oferecerSe(vagaId: string) {
    setEnviando(true)
    setErro(null)
    const { data, error } = await oferecer(vagaId)
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível se oferecer.').detail)
      return
    }
    setVagas((lista) => lista.map((v) => (v.id === vagaId ? (data ?? v) : v)))
    toast.success('Oferta enviada; a coordenadora dona da escala escolhe.')
  }

  async function retirarMinhaOferta(ofertaId: string) {
    setEnviando(true)
    setErro(null)
    const { error } = await retirarOferta(ofertaId)
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível retirar a oferta.').detail)
      return
    }
    toast.success('Oferta retirada.')
    await carregar()
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
      {vagas.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Vagas abertas</CardTitle>
            <CardDescription>
              Escalas de outros times que precisam de gente. A coordenadora dona da escala escolhe quem entra;
              ela não vê os nomes de quem não foi escolhido.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ul className="flex flex-col gap-3">
              {vagas.map((vaga) => {
                const oferta = vaga.minha_oferta
                return (
                  <li key={vaga.id} className="flex flex-col gap-2 rounded-md border p-3">
                    <div className="flex flex-wrap items-center gap-2 text-sm">
                      <span className="font-medium">
                        {formatarData(vaga.sessao?.inicio_previsto)} {formatarHora(vaga.sessao?.inicio_previsto)} – {formatarHora(vaga.sessao?.fim_previsto)}
                      </span>
                      <Badge variant="outline">{rotuloTipoVaga(vaga.tipo)}</Badge>
                      {vaga.ebi_nome && <span className="text-muted-foreground">{vaga.ebi_nome}</span>}
                      {vaga.dona_nome && <span className="text-muted-foreground">· {vaga.dona_nome}</span>}
                    </div>
                    {vaga.observacao && <p className="text-sm">{vaga.observacao}</p>}
                    {(vaga.conflitos ?? []).length > 0 && (
                      <div className="text-xs text-muted-foreground">
                        <p className="font-medium text-foreground">Conflito de horário:</p>
                        {(vaga.conflitos ?? []).map((c) => (
                          <p key={`${c.inicio}-${c.fim}`}>
                            {formatarData(c.inicio)} {formatarHora(c.inicio)} – {formatarHora(c.fim)}
                            {c.ebi_nome ? ` · ${c.ebi_nome}` : ''}
                          </p>
                        ))}
                      </div>
                    )}
                    {oferta ? (
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                          {oferta.status === 'aceita'
                            ? 'Você foi escolhida.'
                            : 'Você se ofereceu; aguardando a coordenação escolher.'}
                        </span>
                        {oferta.status === 'pendente' && oferta.id && (
                          <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => void retirarMinhaOferta(oferta.id!)}>
                            Retirar oferta
                          </Button>
                        )}
                      </div>
                    ) : (
                      <div className="flex flex-wrap items-center gap-2">
                        <Button type="button" size="sm" disabled={enviando} onClick={() => void oferecerSe(vaga.id!)}>
                          Oferecer-se
                        </Button>
                        {(vaga.ofertas_pendentes ?? 0) > 0 && (
                          <span className="text-xs text-muted-foreground">
                            {vaga.ofertas_pendentes} {(vaga.ofertas_pendentes ?? 0) === 1 ? 'pessoa já se ofereceu' : 'pessoas já se ofereceram'}
                          </span>
                        )}
                      </div>
                    )}
                  </li>
                )
              })}
            </ul>
          </CardContent>
        </Card>
      )}
      <Card>
        <CardHeader>
          <CardTitle>Minhas escalas</CardTitle>
          <CardDescription>Confirme sua presença, avise conflitos e peça troca.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-2">
              <Label htmlFor="minhas-inicio">De</Label>
              <Input id="minhas-inicio" type="date" value={inicio} max={fim || undefined} onChange={(e) => setInicio(e.target.value)} />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="minhas-fim">Até</Label>
              <Input id="minhas-fim" type="date" value={fim} min={inicio || undefined} onChange={(e) => setFim(e.target.value)} />
            </div>
          </div>

          {!carregando && linhas.length === 0 && (
            <p className="text-sm text-muted-foreground">Nenhuma escala no período.</p>
          )}

          <ul className="flex flex-col gap-3">
            {linhas.map((linha) => {
              const passada = (linha.inicio_previsto ?? '') < `${hoje}T00:00:00Z`
              const aprovada = linha.escala_status === 'aprovada'
              return (
                <li key={linha.membro_id} className="flex flex-col gap-2 rounded-md border p-3">
                  <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-medium">
                      {formatarData(linha.inicio_previsto)} {formatarHora(linha.inicio_previsto)} – {formatarHora(linha.fim_previsto)}
                    </span>
                    <Badge variant="outline">{linha.funcao === 'coordenadora' ? 'Coordenação' : 'Auxiliar'}</Badge>
                    <Badge variant={aprovada ? 'secondary' : 'outline'}>
                      {aprovada ? 'Aprovada' : linha.escala_status === 'cancelada' ? 'Sessão cancelada' : 'Aguardando aprovação'}
                    </Badge>
                    <span className="text-muted-foreground">{rotuloSituacaoMembro(linha.status)}</span>
                  </div>

                  {!passada && (linha.conflitos ?? []).length > 0 && (
                    <div className="text-xs text-muted-foreground">
                      <p className="font-medium text-foreground">Conflito de horário:</p>
                      {(linha.conflitos ?? []).map((c) => (
                        <p key={`${c.inicio}-${c.fim}`}>
                          {formatarData(c.inicio)} {formatarHora(c.inicio)} – {formatarHora(c.fim)}
                          {c.igreja_nome ? ` · ${c.igreja_nome}` : ''}
                          {c.ebi_nome ? ` · ${c.ebi_nome}` : ''}
                        </p>
                      ))}
                    </div>
                  )}

                  {passada ? (
                    <p className="text-sm">Presença: {textoPresenca(linha.presente)}</p>
                  ) : (
                    <div className="flex flex-wrap items-center gap-2">
                      {aprovada && linha.status === 'escalado' && linha.membro_id && (
                        <Button type="button" size="sm" disabled={enviando} onClick={() => void confirmar(linha.membro_id!)}>
                          Confirmar
                        </Button>
                      )}
                      {aprovada && (linha.status === 'escalado' || linha.status === 'confirmado') && linha.membro_id && (
                        pedindoTroca === linha.membro_id ? (
                          <div className="flex flex-wrap items-end gap-2">
                            <div className="flex flex-col gap-2">
                              <Label htmlFor={`motivo-${linha.membro_id}`}>Motivo (opcional)</Label>
                              <Input
                                id={`motivo-${linha.membro_id}`}
                                value={motivo}
                                maxLength={500}
                                onChange={(e) => setMotivo(e.target.value)}
                              />
                            </div>
                            <Button type="button" size="sm" disabled={enviando} onClick={() => void solicitarTroca(linha.membro_id!)}>
                              Enviar pedido
                            </Button>
                            <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => setPedindoTroca(null)}>
                              Voltar
                            </Button>
                          </div>
                        ) : (
                          <Button type="button" variant="outline" size="sm" disabled={enviando} onClick={() => setPedindoTroca(linha.membro_id!)}>
                            Pedir troca
                          </Button>
                        )
                      )}
                      {linha.status === 'confirmado' && <span className="text-xs text-muted-foreground">Presença confirmada.</span>}
                      {linha.status === 'troca_solicitada' && (
                        <span className="text-xs text-muted-foreground">Troca pedida; aguardando aprovação.</span>
                      )}
                    </div>
                  )}
                </li>
              )
            })}
          </ul>
        </CardContent>
      </Card>
    </section>
  )
}
