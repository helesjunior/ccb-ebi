import { useState } from 'react'
import { AccessibilityIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
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

type Excecao = components['schemas']['ExcecaoEtaria']
type Decisao = components['schemas']['DecisaoExcecaoInput']['decisao']

const DECISOES: { valor: Decisao; rotulo: string }[] = [
  { valor: 'aprovar', rotulo: 'Aprovar' },
  { valor: 'recusar', rotulo: 'Recusar' },
  { valor: 'suspender', rotulo: 'Suspender' },
  { valor: 'revogar', rotulo: 'Revogar' },
]

/**
 * Exceção inclusiva (FL-06, ADR-0012): o responsável solicita, a coordenadora
 * decide. A exceção libera a presença de quem completou 12 anos, mas **nunca**
 * libera o bloqueio de menores de 3 anos (CA-19). Estados suspensa/revogada/
 * expirada deixam de valer (RN-15).
 */
export function ExcecaoInclusiva() {
  const [participanteId, setParticipanteId] = useState('')
  const [tipoNecessidade, setTipoNecessidade] = useState('neurodivergencia')
  const [apoios, setApoios] = useState('')

  const [excecaoId, setExcecaoId] = useState('')
  const [decisao, setDecisao] = useState<Decisao>('aprovar')
  const [vigenteAte, setVigenteAte] = useState('')
  const [motivo, setMotivo] = useState('')

  const [excecao, setExcecao] = useState<Excecao | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  async function solicitar() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.POST('/participants/{id}/age-exceptions', {
      params: { path: { id: participanteId } },
      headers: cabecalhos(),
      body: { tipo_necessidade: tipoNecessidade, apoios_necessarios: apoios || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível solicitar a exceção.').detail)
      return
    }
    setExcecao(data)
    setApoios('')
  }

  async function decidir() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.PATCH('/age-exceptions/{id}', {
      params: { path: { id: excecaoId } },
      headers: cabecalhos(),
      body: {
        decisao,
        vigente_ate: vigenteAte || undefined,
        motivo: motivo || undefined,
      },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível decidir a exceção.').detail)
      return
    }
    setExcecao(data)
    setMotivo('')
  }

  return (
    <section className="mx-auto flex max-w-2xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}
      {excecao && (
        <Alert role="status" data-testid="excecao-situacao">
          <AlertDescription>
            Exceção {excecao.id}: {excecao.status}
            {excecao.vigente_ate ? ` · vigente até ${excecao.vigente_ate}` : ''}
          </AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <AccessibilityIcon className="size-5" aria-hidden="true" />
            Solicitar (responsável)
          </CardTitle>
          <CardDescription>Peça a exceção inclusiva para um participante.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-participante">ID do participante</Label>
            <Input
              id="excecao-participante"
              value={participanteId}
              onChange={(e) => setParticipanteId(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-tipo">Tipo de necessidade</Label>
            <Input
              id="excecao-tipo"
              value={tipoNecessidade}
              onChange={(e) => setTipoNecessidade(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-apoios">Apoios necessários (opcional)</Label>
            <textarea
              id="excecao-apoios"
              value={apoios}
              onChange={(e) => setApoios(e.target.value)}
              className="min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
            />
          </div>
          <Button
            type="button"
            onClick={solicitar}
            disabled={ocupado || !participanteId || !tipoNecessidade}
            className="self-start"
          >
            Solicitar exceção
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Decidir (coordenadora)</CardTitle>
          <CardDescription>Aprove, recuse, suspenda ou revogue a exceção.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-id">ID da exceção</Label>
            <Input
              id="excecao-id"
              value={excecaoId}
              onChange={(e) => setExcecaoId(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-decisao">Decisão</Label>
            <Select value={decisao} onValueChange={(v) => setDecisao(v as Decisao)}>
              <SelectTrigger id="excecao-decisao" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {DECISOES.map((d) => (
                  <SelectItem key={d.valor} value={d.valor}>
                    {d.rotulo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-vigencia">Vigente até (opcional)</Label>
            <Input
              id="excecao-vigencia"
              type="date"
              value={vigenteAte}
              onChange={(e) => setVigenteAte(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="excecao-motivo">Motivo (opcional)</Label>
            <Input id="excecao-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
          </div>
          <Button
            type="button"
            onClick={decidir}
            disabled={ocupado || !excecaoId}
            className="self-start"
          >
            Decidir
          </Button>
        </CardContent>
      </Card>
    </section>
  )
}