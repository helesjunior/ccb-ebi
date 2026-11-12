import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { MailPlusIcon, UsersIcon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type ContextoAtuacao, type Usuario } from '../api/sessao'
import { ConfirmarAcao } from '../components/ConfirmarAcao'
import { EstadoTabela } from '../components/EstadoTabela'
import { Obrigatorio } from '../components/Obrigatorio'
import { PAPEIS_CONHECIDOS, rotuloPapel, rotuloStatus, rotuloTipoEscopo } from '@/lib/formatos'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
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
  const [carregando, setCarregando] = useState(true)

  const carregar = useCallback(async () => {
    const [u, c] = await Promise.all([
      api.GET('/users', { headers: cabecalhos() }),
      api.GET('/invitations', { headers: cabecalhos() }),
    ])
    setCarregando(false)
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
    toast.success(`Convite enviado para ${email}.`)
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
    toast.success('Convite cancelado.')
    await carregar()
  }

  const podeConvidar = email.trim() !== '' && papel !== '' && escopoId.trim() !== ''

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive" role="alert">
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
                <TableHead className="hidden sm:table-cell">E-mail</TableHead>
                <TableHead>Status</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={3}
                carregando={carregando}
                vazio={usuarios.length === 0}
                mensagemVazio="Nenhum usuário neste escopo."
              />
              {usuarios.map((u) => (
                <TableRow key={u.id}>
                  <TableCell className="font-medium whitespace-normal">
                    {u.nome}
                    <span className="block text-xs font-normal text-muted-foreground sm:hidden">
                      {u.email}
                    </span>
                  </TableCell>
                  <TableCell className="hidden sm:table-cell">{u.email}</TableCell>
                  <TableCell>
                    <Badge variant={u.status === 'ativo' ? 'secondary' : 'outline'}>
                      {rotuloStatus(u.status)}
                    </Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MailPlusIcon className="size-5" aria-hidden="true" />
            Convidar
          </CardTitle>
          <CardDescription>
            A pessoa recebe um e-mail com o link para criar o acesso. Campos com{' '}
            <Obrigatorio /> são obrigatórios.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form onSubmit={convidar} noValidate className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-2 sm:col-span-2">
              <Label htmlFor="convite-email">
                E-mail <Obrigatorio />
              </Label>
              <Input
                id="convite-email"
                type="email"
                autoComplete="off"
                placeholder="nome@exemplo.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-papel">
                Papel <Obrigatorio />
              </Label>
              <Select value={papel} onValueChange={setPapel}>
                <SelectTrigger id="convite-papel" className="w-full" aria-required="true">
                  <SelectValue placeholder="Escolha o papel" />
                </SelectTrigger>
                <SelectContent>
                  {PAPEIS_CONHECIDOS.map((p) => (
                    <SelectItem key={p} value={p}>
                      {rotuloPapel(p)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="convite-escopo-tipo">
                Tipo de escopo <Obrigatorio />
              </Label>
              <Select value={tipoEscopo} onValueChange={(v) => setTipoEscopo(v as TipoEscopo)}>
                <SelectTrigger id="convite-escopo-tipo" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TIPOS_ESCOPO.map((t) => (
                    <SelectItem key={t} value={t}>
                      {rotuloTipoEscopo(t)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2 sm:col-span-2">
              <Label htmlFor="convite-escopo-id">
                ID do escopo <Obrigatorio />
              </Label>
              <Input
                id="convite-escopo-id"
                value={escopoId}
                onChange={(e) => setEscopoId(e.target.value)}
                aria-describedby="convite-escopo-id-ajuda"
                autoComplete="off"
                spellCheck={false}
                className="font-mono"
                required
              />
              <p id="convite-escopo-id-ajuda" className="text-xs text-muted-foreground">
                Identificador da {rotuloTipoEscopo(tipoEscopo).toLowerCase()} onde a pessoa vai atuar.
              </p>
            </div>
            <Button
              type="submit"
              disabled={enviando || !podeConvidar}
              className="sm:col-span-2 sm:justify-self-start"
            >
              {enviando ? 'Enviando…' : 'Enviar convite'}
            </Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Convites pendentes</CardTitle>
          <CardDescription>Convites enviados que ainda não foram aceitos.</CardDescription>
        </CardHeader>
        <CardContent>
          <Table data-testid="convites">
            <TableHeader>
              <TableRow>
                <TableHead>Convite</TableHead>
                <TableHead className="text-right">
                  <span className="sr-only">Ações</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={2}
                carregando={carregando}
                vazio={convites.length === 0}
                mensagemVazio="Nenhum convite pendente."
              />
              {convites.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="whitespace-normal">
                    <span className="block font-medium">{c.email}</span>
                    <span className="text-xs text-muted-foreground">
                      {rotuloPapel(c.papel)} · {rotuloTipoEscopo(c.tipo_escopo)}{' '}
                      <code title={c.escopo_id}>{c.escopo_id?.slice(-6)}</code>
                    </span>
                  </TableCell>
                  <TableCell className="text-right">
                    <ConfirmarAcao
                      titulo="Cancelar este convite?"
                      descricao={`O link enviado para ${c.email ?? 'esta pessoa'} deixará de funcionar.`}
                      rotuloConfirmar="Cancelar convite"
                      onConfirmar={() => (c.id ? cancelarConvite(c.id) : undefined)}
                    >
                      <Button type="button" variant="outline" size="sm">
                        Cancelar
                      </Button>
                    </ConfirmarAcao>
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
