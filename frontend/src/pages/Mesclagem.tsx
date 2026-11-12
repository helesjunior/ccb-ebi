import { useState } from 'react'
import { CombineIcon, Undo2Icon } from 'lucide-react'

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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

type Participante = components['schemas']['Participante']

/**
 * Mesclagem (CA-27): busca candidatos por nome + data, escolhe o principal e o
 * duplicado, mescla e pode desfazer. Toda mesclagem é auditada e reversível no
 * servidor (o duplicado vira `mesclado`; desfazer restaura o estado anterior).
 */
export function Mesclagem() {
  const [termo, setTermo] = useState('')
  const [dataNascimento, setDataNascimento] = useState('')
  const [finalidade, setFinalidade] = useState('deduplicacao')
  const [candidatos, setCandidatos] = useState<Participante[]>([])
  const [principalId, setPrincipalId] = useState('')
  const [duplicadoId, setDuplicadoId] = useState('')
  const [mensagem, setMensagem] = useState<string | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  const finalidadeValida = finalidade.trim() !== ''

  async function buscar() {
    setErro(null)
    setMensagem(null)
    if (!finalidadeValida) {
      setErro('Informe a finalidade da leitura.')
      return
    }
    setOcupado(true)
    const { data, error } = await api.GET('/participants', {
      params: {
        query: {
          q: termo,
          finalidade,
          data_nascimento: dataNascimento || undefined,
        },
      },
      headers: cabecalhos(),
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível buscar candidatos.').detail)
      return
    }
    setCandidatos(data.data ?? [])
  }

  async function mesclar() {
    setErro(null)
    setMensagem(null)
    setOcupado(true)
    const { error } = await api.POST('/participants/{id}/merge', {
      params: { path: { id: principalId } },
      headers: cabecalhos(),
      body: { duplicado_id: duplicadoId },
    })
    setOcupado(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível mesclar.').detail)
      return
    }
    setMensagem('Mesclagem concluída. É possível desfazer.')
    await buscar()
  }

  async function desfazer() {
    if (!principalId) return
    setErro(null)
    setMensagem(null)
    setOcupado(true)
    const { error } = await api.POST('/participants/{id}/merge/undo', {
      params: { path: { id: principalId } },
      headers: cabecalhos(),
    })
    setOcupado(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível desfazer a mesclagem.').detail)
      return
    }
    setMensagem('Mesclagem desfeita.')
    await buscar()
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}
      {mensagem && (
        <Alert role="status">
          <AlertDescription>{mensagem}</AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <CombineIcon className="size-5" aria-hidden="true" />
            Candidatos
          </CardTitle>
          <CardDescription>Busque possíveis duplicados por nome e data.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="mescla-termo">Nome</Label>
            <Input id="mescla-termo" value={termo} onChange={(e) => setTermo(e.target.value)} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="mescla-nascimento">Data de nascimento (opcional)</Label>
            <Input
              id="mescla-nascimento"
              type="date"
              value={dataNascimento}
              onChange={(e) => setDataNascimento(e.target.value)}
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="mescla-finalidade">Finalidade da leitura</Label>
            <Input
              id="mescla-finalidade"
              value={finalidade}
              onChange={(e) => setFinalidade(e.target.value)}
              required
            />
          </div>
          <Button
            type="button"
            onClick={buscar}
            disabled={ocupado || !termo}
            className="self-start"
          >
            Buscar
          </Button>

          <Table data-testid="candidatos">
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Nascimento</TableHead>
                <TableHead>Matrícula</TableHead>
                <TableHead aria-label="Escolher" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {candidatos.map((p) => (
                <TableRow key={p.id}>
                  <TableCell>{p.nome}</TableCell>
                  <TableCell>{p.data_nascimento}</TableCell>
                  <TableCell>{p.matricula}</TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setPrincipalId(p.id)}
                      >
                        principal
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setDuplicadoId(p.id)}
                      >
                        duplicado
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Mesclar</CardTitle>
          <CardDescription>
            Principal: <code data-testid="escolhidos">{principalId || '—'}</code> · Duplicado:{' '}
            <code>{duplicadoId || '—'}</code>
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          <Button
            type="button"
            onClick={mesclar}
            disabled={ocupado || !principalId || !duplicadoId || principalId === duplicadoId}
          >
            Mesclar
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={desfazer}
            disabled={ocupado || !principalId}
          >
            <Undo2Icon className="size-4" aria-hidden="true" />
            Desfazer última mesclagem
          </Button>
        </CardContent>
      </Card>
    </section>
  )
}