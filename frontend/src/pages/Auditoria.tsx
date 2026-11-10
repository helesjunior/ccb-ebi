import { useCallback, useEffect, useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

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
  const [carregando, setCarregando] = useState(false)

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
      <main>
        <h1>Auditoria</h1>
        <p role="alert" data-testid="auditoria-sem-permissao">
          Você não tem permissão para consultar a trilha de auditoria.
        </p>
      </main>
    )
  }

  return (
    <main>
      <h1>Auditoria</h1>
      {erro && <p role="alert">{erro}</p>}

      <form onSubmit={filtrar} noValidate>
        <p>
          <label htmlFor="auditoria-recurso">Recurso</label>
          <input
            id="auditoria-recurso"
            value={filtros.recurso}
            onChange={(e) => setFiltros({ ...filtros, recurso: e.target.value })}
          />
        </p>
        <p>
          <label htmlFor="auditoria-ator">Ator</label>
          <input
            id="auditoria-ator"
            value={filtros.ator_id}
            onChange={(e) => setFiltros({ ...filtros, ator_id: e.target.value })}
          />
        </p>
        <p>
          <label htmlFor="auditoria-de">De</label>
          <input
            id="auditoria-de"
            type="datetime-local"
            value={filtros.de}
            onChange={(e) => setFiltros({ ...filtros, de: e.target.value })}
          />
        </p>
        <p>
          <label htmlFor="auditoria-ate">Até</label>
          <input
            id="auditoria-ate"
            type="datetime-local"
            value={filtros.ate}
            onChange={(e) => setFiltros({ ...filtros, ate: e.target.value })}
          />
        </p>
        <button type="submit" disabled={carregando}>
          Filtrar
        </button>
      </form>

      <ul data-testid="eventos-auditoria">
        {eventos.map((ev) => (
          <li key={ev.id}>
            <span>
              #{ev.sequencia} — {new Date(ev.ocorrido_em).toLocaleString('pt-BR')} —{' '}
              <strong>{ev.acao}</strong> em {ev.recurso} ({ev.origem})
            </span>
          </li>
        ))}
      </ul>

      {cursor && (
        <button
          type="button"
          onClick={() => carregar(aplicados, cursor, true)}
          disabled={carregando}
        >
          {carregando ? 'Carregando…' : 'Carregar mais'}
        </button>
      )}
    </main>
  )
}