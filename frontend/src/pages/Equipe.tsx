import { useCallback, useEffect, useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type ContextoAtuacao, type Usuario } from '../api/sessao'

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
    <main>
      <h1>Equipe</h1>
      {erro && <p role="alert">{erro}</p>}

      <h2>Usuários</h2>
      <ul data-testid="usuarios">
        {usuarios.map((u) => (
          <li key={u.id}>
            {u.nome} — {u.email} — {u.status}
          </li>
        ))}
      </ul>

      <h2>Convidar</h2>
      <form onSubmit={convidar} noValidate>
        <p>
          <label htmlFor="convite-email">E-mail</label>
          <input
            id="convite-email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="convite-papel">Papel</label>
          <input
            id="convite-papel"
            value={papel}
            onChange={(e) => setPapel(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="convite-escopo-tipo">Tipo de escopo</label>
          <select
            id="convite-escopo-tipo"
            value={tipoEscopo}
            onChange={(e) => setTipoEscopo(e.target.value as TipoEscopo)}
          >
            {TIPOS_ESCOPO.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </p>
        <p>
          <label htmlFor="convite-escopo-id">ID do escopo</label>
          <input
            id="convite-escopo-id"
            value={escopoId}
            onChange={(e) => setEscopoId(e.target.value)}
            required
          />
        </p>
        <button type="submit" disabled={enviando}>
          {enviando ? 'Enviando…' : 'Convidar'}
        </button>
      </form>

      <h2>Convites</h2>
      <ul data-testid="convites">
        {convites.map((c) => (
          <li key={c.id}>
            <span>
              {c.email} — {c.papel} @ {c.tipo_escopo}:{c.escopo_id}
            </span>
            <button type="button" onClick={() => c.id && cancelarConvite(c.id)}>
              Cancelar
            </button>
          </li>
        ))}
      </ul>
    </main>
  )
}