import { useState } from 'react'
import { LinkIcon } from 'lucide-react'

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

type Vinculo = components['schemas']['ResponsavelParticipante']
type Decisao = components['schemas']['DecisaoVinculoInput']['decisao']

const DECISOES: { valor: Decisao; rotulo: string }[] = [
  { valor: 'aprovar', rotulo: 'Aprovar' },
  { valor: 'recusar', rotulo: 'Recusar' },
  { valor: 'pedir_documento', rotulo: 'Pedir documento' },
  { valor: 'revogar', rotulo: 'Revogar' },
  { valor: 'contestar', rotulo: 'Contestar' },
]

/**
 * Vínculos: pedido de vínculo do responsável e decisão da coordenadora (RF-03).
 * Um vínculo só libera acesso após `aprovada` (CA-04). Contestar ou revogar
 * encerra o vínculo (`fim`) sem apagar o histórico.
 */
export function Vinculos() {
  const [participanteId, setParticipanteId] = useState('')
  const [vinculoId, setVinculoId] = useState('')
  const [decisao, setDecisao] = useState<Decisao>('aprovar')
  const [motivo, setMotivo] = useState('')

  const [nome, setNome] = useState('')
  const [tipoRelacao, setTipoRelacao] = useState('responsavel')
  const [telefone, setTelefone] = useState('')

  const [vinculo, setVinculo] = useState<Vinculo | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  async function solicitar() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.POST('/participants/{id}/guardians', {
      params: { path: { id: participanteId } },
      headers: cabecalhos(),
      body: { nome, tipo_relacao: tipoRelacao, telefone: telefone || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível solicitar o vínculo.').detail)
      return
    }
    setVinculo(data)
    setNome('')
    setTelefone('')
  }

  async function decidir() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.PATCH('/guardian-requests/{id}', {
      params: { path: { id: vinculoId } },
      headers: cabecalhos(),
      body: { decisao, motivo: motivo || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível decidir o vínculo.').detail)
      return
    }
    setVinculo(data)
    setMotivo('')
  }

  return (
    <section className="mx-auto flex max-w-2xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}
      {vinculo && (
        <Alert role="status" data-testid="vinculo-situacao">
          <AlertDescription>
            Vínculo {vinculo.id}: {vinculo.validacao}{' '}
            {vinculo.fim ? `(encerrado em ${vinculo.fim})` : ''}
          </AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <LinkIcon className="size-5" aria-hidden="true" />
            Solicitar vínculo
          </CardTitle>
          <CardDescription>Peça o vínculo de responsável com um participante.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="vinculo-participante">ID do participante</Label>
            <Input
              id="vinculo-participante"
              value={participanteId}
              onChange={(e) => setParticipanteId(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="vinculo-nome">Seu nome</Label>
            <Input
              id="vinculo-nome"
              value={nome}
              onChange={(e) => setNome(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="vinculo-relacao">Tipo de relação</Label>
            <Input
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
              value={telefone}
              onChange={(e) => setTelefone(e.target.value)}
            />
          </div>
          <Button
            type="button"
            onClick={solicitar}
            disabled={ocupado || !participanteId || !nome || !tipoRelacao}
            className="self-start"
          >
            Solicitar
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Decidir pedido</CardTitle>
          <CardDescription>Aprove, recuse ou encerre o vínculo solicitado.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="vinculo-id">ID do vínculo</Label>
            <Input
              id="vinculo-id"
              value={vinculoId}
              onChange={(e) => setVinculoId(e.target.value)}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="vinculo-decisao">Decisão</Label>
            <Select value={decisao} onValueChange={(v) => setDecisao(v as Decisao)}>
              <SelectTrigger id="vinculo-decisao" className="w-full">
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
            <Label htmlFor="vinculo-motivo">Motivo (opcional)</Label>
            <Input
              id="vinculo-motivo"
              value={motivo}
              onChange={(e) => setMotivo(e.target.value)}
            />
          </div>
          <Button
            type="button"
            onClick={decidir}
            disabled={ocupado || !vinculoId}
            className="self-start"
          >
            Decidir
          </Button>
        </CardContent>
      </Card>
    </section>
  )
}