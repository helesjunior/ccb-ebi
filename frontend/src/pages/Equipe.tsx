import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { UsersIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type ContextoAtuacao, type Usuario } from '../api/sessao'
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

interface Convite {
  id?: string
  email?: string
  papel?: string
  tipo_escopo?: ContextoAtuacao['tipo_escopo']
  escopo_id?: string
  pendente?: boolean
}

type TipoEscopo = NonNullable<ContextoAtuacao['tipo_escopo']>

const TIPOS_ESCOPO: TipoEscopo[] = ['central', 'regional', 'setor', 'igreja', 'ebi']

/**
 * Equipe: lista usuários do escopo, convida pessoas e encerra convites.
 * Toda chamada envia o contexto de atuação (`X-Acting-Scope`) e o servidor
 * revalida a autoridade por papel e escopo.
 */
export function Equipe() {
  const [usuarios, setUsuarios] = useState<Usuario[]>([])
  const [convites, setConvites] = useState<Convite[]>([])
  const [email, setEmail] = useState('')
  const [papel, setPapel] = useState('')
  const [tipoEscopo, setTipoEscopo] = useState<TipoEscopo>('igreja')
  const [escopoId, setEscopoId] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  const carregar = useCallback(async () => {
    const [u, c] = await Promise.all([
      api.GET('/users', { headers: cabecalhos() }),
      api.GET('/invitations', { headers: cabecalhos() }),
    ])
    if (u.error || !u.data) {
      setErro(lerErro(u.error, 'Não foi possível carregar a equipe.').detail)
      return
    }
    setUsuarios(u.data.data ?? [])
    if (!c.error && c.data) {
      setConvites(c.data.data ?? [])
    }
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  async function convidar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)
    const { error } = await api.POST('/invitations', {
      headers: cabecalhos(),
      body: { email, papel, tipo_escopo: tipoEscopo, escopo_id: escopoId },
    })
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível criar o convite.').detail)
      return
    }
    setEmail('')
    setPapel('')
    setEscopoId('')
    await carregar()
  }

  async function cancelarConvite(id: string) {
    const { error } = await api.DELETE('/invitations/{id}', {
      params: { path: { id } },
      headers: cabecalhos(),
    })
    if (error) {
      setErro(lerErro(error, 'Não foi possível cancelar o convite.').detail)
      return
    }
    await carregar()
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UsersIcon className="size-5" aria-hidden="true" />
            Usuários
          </CardTitle>
          <CardDescription>Pessoas com acesso no escopo de atuação atual.</CardDescription>
        </CardHeader>
        <CardContent>
          <Table data-testid="usuarios">
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>E-mail</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {usuarios.map((u) => (
                <TableRow key={u.id}>
                  <TableCell>{u.nome}</TableCell>
                  <TableCell>{u.email}</TableCell>
                  <TableCell>{u.status}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Convidar</CardTitle>
          <CardDescription>Envie um convite para uma pessoa entrar no escopo.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form onSubmit={convidar} noValidate className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-email">E-mail</Label>
              <Input
                id="convite-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-papel">Papel</Label>
              <Input
                id="convite-papel"
                value={papel}
                onChange={(e) => setPapel(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-escopo-tipo">Tipo de escopo</Label>
              <Select value={tipoEscopo} onValueChange={(v) => setTipoEscopo(v as TipoEscopo)}>
                <SelectTrigger id="convite-escopo-tipo" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TIPOS_ESCOPO.map((t) => (
                    <SelectItem key={t} value={t}>
                      {t}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-escopo-id">ID do escopo</Label>
              <Input
                id="convite-escopo-id"
                value={escopoId}
                onChange={(e) => setEscopoId(e.target.value)}
                required
              />
            </div>
            <Button type="submit" disabled={enviando} className="self-start">
              {enviando ? 'Enviando…' : 'Convidar'}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Convites</CardTitle>
          <CardDescription>Convites pendentes neste escopo.</CardDescription>
        </CardHeader>
        <CardContent>
          <Table data-testid="convites">
            <TableHeader>
              <TableRow>
                <TableHead>Convite</TableHead>
                <TableHead aria-label="Ações" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {convites.map((c) => (
                <TableRow key={c.id}>
                  <TableCell>
                    {c.email} — {c.papel} @ {c.tipo_escopo}:{c.escopo_id}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => c.id && cancelarConvite(c.id)}
                    >
                      Cancelar
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </section>
  )
}