import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { ScrollTextIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { EstadoTabela } from '../components/EstadoTabela'
import { formatarDataHora } from '@/lib/formatos'
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

type EventoAuditoria = components['schemas']['EventoAuditoria']

/** Filtros do formulário de consulta da trilha. */
interface Filtros {
  recurso: string
  ator_id: string
  de: string
  ate: string
}

const FILTROS_VAZIOS: Filtros = { recurso: '', ator_id: '', de: '', ate: '' }

/**
 * Auditoria: consulta a trilha append-only com filtros e paginação por cursor
 * (`meta.proximo_cursor`). Visível apenas a perfis com a operação `auditoria.ler`;
 * o servidor revalida a autoridade por papel e escopo, devolvendo 403 caso o
 * perfil não tenha a operação — tratado aqui com uma mensagem própria.
 */
export function Auditoria() {
  const [eventos, setEventos] = useState<EventoAuditoria[]>([])
  const [cursor, setCursor] = useState<string | null>(null)
  const [filtros, setFiltros] = useState<Filtros>(FILTROS_VAZIOS)
  const [aplicados, setAplicados] = useState<Filtros>(FILTROS_VAZIOS)
  const [erro, setErro] = useState<string | null>(null)
  const [semPermissao, setSemPermissao] = useState(false)
  const [carregando, setCarregando] = useState(true)

  /** Monta a query omitindo filtros vazios e o cursor quando ausente. */
  const carregar = useCallback(async (base: Filtros, proximo: string | null, acumular: boolean) => {
    setCarregando(true)
    setErro(null)
    const { data, error } = await api.GET('/audit-events', {
      params: {
        query: {
          recurso: base.recurso || undefined,
          ator_id: base.ator_id || undefined,
          de: base.de || undefined,
          ate: base.ate || undefined,
          cursor: proximo ?? undefined,
        },
      },
      headers: cabecalhos(),
    })
    setCarregando(false)
    if (error || !data) {
      const problema = lerErro(error, 'Não foi possível carregar os eventos de auditoria.')
      if (problema.status === 403) {
        setSemPermissao(true)
        return
      }
      setErro(problema.detail)
      return
    }
    setSemPermissao(false)
    const pagina = data.data ?? []
    setEventos((anteriores) => (acumular ? [...anteriores, ...pagina] : pagina))
    setCursor(data.meta?.proximo_cursor ?? null)
  }, [])

  useEffect(() => {
    void carregar(FILTROS_VAZIOS, null, false)
  }, [carregar])

  function filtrar(evento: FormEvent) {
    evento.preventDefault()
    const base: Filtros = {
      recurso: filtros.recurso.trim(),
      ator_id: filtros.ator_id.trim(),
      de: filtros.de,
      ate: filtros.ate,
    }
    setAplicados(base)
    void carregar(base, null, false)
  }

  if (semPermissao) {
    return (
      <section className="mx-auto flex max-w-4xl flex-col gap-6">
        <Alert variant="destructive" role="alert" data-testid="auditoria-sem-permissao">
          <AlertDescription>
            Você não tem permissão para consultar a trilha de auditoria.
          </AlertDescription>
        </Alert>
      </section>
    )
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ScrollTextIcon className="size-5" aria-hidden="true" />
            Auditoria
          </CardTitle>
          <CardDescription>Trilha append-only de eventos do sistema.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {erro && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          <form onSubmit={filtrar} noValidate className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-2">
              <Label htmlFor="auditoria-recurso">Recurso</Label>
              <Input
                id="auditoria-recurso"
                value={filtros.recurso}
                onChange={(e) => setFiltros({ ...filtros, recurso: e.target.value })}
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="auditoria-ator">Ator</Label>
              <Input
                id="auditoria-ator"
                value={filtros.ator_id}
                onChange={(e) => setFiltros({ ...filtros, ator_id: e.target.value })}
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="auditoria-de">De</Label>
              <Input
                id="auditoria-de"
                type="datetime-local"
                value={filtros.de}
                onChange={(e) => setFiltros({ ...filtros, de: e.target.value })}
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="auditoria-ate">Até</Label>
              <Input
                id="auditoria-ate"
                type="datetime-local"
                value={filtros.ate}
                onChange={(e) => setFiltros({ ...filtros, ate: e.target.value })}
              />
            </div>
            <div className="flex flex-wrap gap-2 sm:col-span-2">
              <Button type="submit" disabled={carregando}>
                Filtrar
              </Button>
              <Button
                type="button"
                variant="ghost"
                disabled={carregando}
                onClick={() => {
                  setFiltros(FILTROS_VAZIOS)
                  setAplicados(FILTROS_VAZIOS)
                  void carregar(FILTROS_VAZIOS, null, false)
                }}
              >
                Limpar filtros
              </Button>
            </div>
          </form>

          <Table data-testid="eventos-auditoria">
            <TableHeader>
              <TableRow>
                <TableHead className="hidden sm:table-cell">Seq.</TableHead>
                <TableHead>Data</TableHead>
                <TableHead>Ação</TableHead>
                <TableHead>Recurso</TableHead>
                <TableHead className="hidden md:table-cell">Origem</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={5}
                carregando={carregando && eventos.length === 0}
                vazio={eventos.length === 0}
                mensagemVazio="Nenhum evento encontrado com esses filtros."
              />
              {eventos.map((ev) => (
                <TableRow key={ev.id}>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">
                    #{ev.sequencia}
                  </TableCell>
                  <TableCell>{formatarDataHora(ev.ocorrido_em)}</TableCell>
                  <TableCell className="font-medium">{ev.acao}</TableCell>
                  <TableCell>{ev.recurso}</TableCell>
                  <TableCell className="hidden md:table-cell">{ev.origem}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {cursor && (
            <Button
              type="button"
              variant="outline"
              onClick={() => carregar(aplicados, cursor, true)}
              disabled={carregando}
              className="self-start"
            >
              {carregando ? 'Carregando…' : 'Carregar mais'}
            </Button>
          )}
        </CardContent>
      </Card>
    </section>
  )
}