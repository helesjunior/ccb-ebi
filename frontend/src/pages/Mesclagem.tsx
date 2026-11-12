import { useState, type FormEvent } from 'react'
import { CheckIcon, CombineIcon, SearchIcon, Undo2Icon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { ConfirmarAcao } from '../components/ConfirmarAcao'
import { EstadoTabela } from '../components/EstadoTabela'
import { Sugestoes } from '../components/Sugestoes'
import { formatarData, SUGESTOES_FINALIDADE } from '@/lib/formatos'
import { cn } from '@/lib/utils'
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
  const [buscou, setBuscou] = useState(false)

  const principal = candidatos.find((p) => p.id === principalId)
  const duplicado = candidatos.find((p) => p.id === duplicadoId)

  const finalidadeValida = finalidade.trim() !== ''

  async function buscar(evento?: FormEvent) {
    evento?.preventDefault()
    if (!termo.trim()) return
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
    setBuscou(true)
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
    toast.success('Cadastros mesclados.')
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
    toast.success('Mesclagem desfeita.')
    await buscar()
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive" role="alert">
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
          <form onSubmit={buscar} role="search" noValidate className="grid gap-4 sm:grid-cols-3 sm:items-end">
          <div className="flex flex-col gap-2">
            <Label htmlFor="mescla-termo">Nome</Label>
            <Input
              id="mescla-termo"
              type="search"
              autoComplete="off"
              value={termo}
              onChange={(e) => setTermo(e.target.value)}
            />
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
              list="mescla-finalidade-sugestoes"
              value={finalidade}
              onChange={(e) => setFinalidade(e.target.value)}
              required
            />
            <Sugestoes id="mescla-finalidade-sugestoes" opcoes={SUGESTOES_FINALIDADE} />
          </div>
          <Button type="submit" disabled={ocupado || !termo.trim()} className="sm:col-span-3 sm:justify-self-start">
            <SearchIcon className="size-4" aria-hidden="true" />
            Buscar candidatos
          </Button>
          </form>
          <p className="text-sm text-muted-foreground">
            Escolha qual cadastro fica (<strong>principal</strong>) e qual será absorvido (
            <strong>duplicado</strong>).
          </p>

          <Table data-testid="candidatos">
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Nascimento</TableHead>
                <TableHead>Matrícula</TableHead>
                <TableHead className="text-right">
                  <span className="sr-only">Escolher</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={4}
                carregando={ocupado && candidatos.length === 0}
                vazio={candidatos.length === 0}
                mensagemVazio={buscou ? 'Nenhum candidato encontrado.' : 'Busque por nome para listar candidatos.'}
              />
              {candidatos.map((p) => (
                <TableRow
                  key={p.id}
                  data-state={p.id === principalId || p.id === duplicadoId ? 'selected' : undefined}
                >
                  <TableCell className="font-medium whitespace-normal">{p.nome}</TableCell>
                  <TableCell>{formatarData(p.data_nascimento)}</TableCell>
                  <TableCell>
                    <code>{p.matricula}</code>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        type="button"
                        variant={p.id === principalId ? 'default' : 'outline'}
                        size="sm"
                        aria-pressed={p.id === principalId}
                        aria-label={`Marcar ${p.nome} como principal`}
                        onClick={() => {
                          setPrincipalId(p.id)
                          if (duplicadoId === p.id) setDuplicadoId('')
                        }}
                      >
                        {p.id === principalId && <CheckIcon className="size-3" aria-hidden="true" />}
                        Principal
                      </Button>
                      <Button
                        type="button"
                        variant={p.id === duplicadoId ? 'destructive' : 'outline'}
                        size="sm"
                        aria-pressed={p.id === duplicadoId}
                        aria-label={`Marcar ${p.nome} como duplicado`}
                        onClick={() => {
                          setDuplicadoId(p.id)
                          if (principalId === p.id) setPrincipalId('')
                        }}
                      >
                        {p.id === duplicadoId && <CheckIcon className="size-3" aria-hidden="true" />}
                        Duplicado
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
          <CardDescription>O duplicado passa a apontar para o principal. Dá para desfazer depois.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <dl className="grid gap-3 text-sm sm:grid-cols-2">
            <div className={cn('rounded-lg border p-3', principalId && 'border-primary/50 bg-primary/5')}>
              <dt className="text-xs font-medium text-muted-foreground uppercase">Principal (fica)</dt>
              <dd className="mt-1 font-medium">{principal?.nome ?? (principalId ? '' : 'Não escolhido')}</dd>
              <dd>
                <code data-testid="escolhidos">{principalId || '—'}</code>
              </dd>
            </div>
            <div className={cn('rounded-lg border p-3', duplicadoId && 'border-destructive/50 bg-destructive/5')}>
              <dt className="text-xs font-medium text-muted-foreground uppercase">Duplicado (é absorvido)</dt>
              <dd className="mt-1 font-medium">{duplicado?.nome ?? (duplicadoId ? '' : 'Não escolhido')}</dd>
              <dd>
                <code>{duplicadoId || '—'}</code>
              </dd>
            </div>
          </dl>
          <div className="flex flex-wrap gap-2">
          <ConfirmarAcao
            titulo="Mesclar estes cadastros?"
            descricao={`${duplicado?.nome ?? 'O duplicado'} será absorvido por ${principal?.nome ?? 'o principal'}. A operação fica registrada na auditoria e pode ser desfeita.`}
            rotuloConfirmar="Mesclar"
            onConfirmar={mesclar}
          >
            <Button
              type="button"
              disabled={ocupado || !principalId || !duplicadoId || principalId === duplicadoId}
            >
              <CombineIcon className="size-4" aria-hidden="true" />
              Mesclar
            </Button>
          </ConfirmarAcao>
          <Button
            type="button"
            variant="outline"
            onClick={desfazer}
            disabled={ocupado || !principalId}
          >
            <Undo2Icon className="size-4" aria-hidden="true" />
            Desfazer última mesclagem
          </Button>
          </div>
        </CardContent>
      </Card>
    </section>
  )
}