import { useCallback, useEffect, useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'

interface SessaoLogin {
  id?: string
  dispositivo?: string
  criada_em?: string
  expira_em?: string
}

/**
 * Dispositivos: lista as sessões vigentes e permite revogar cada uma
 * individualmente (RF-05).
 */
export function Dispositivos() {
  const [sessoes, setSessoes] = useState<SessaoLogin[]>([])
  const [erro, setErro] = useState<string | null>(null)

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/sessions', { headers: cabecalhos() })
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível carregar os dispositivos.').detail)
      return
    }
    setSessoes(data.data ?? [])
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  async function revogar(id: string) {
    const { error } = await api.DELETE('/me/sessions/{id}', {
      params: { path: { id } },
      headers: cabecalhos(),
    })
    if (error) {
      setErro(lerErro(error, 'Não foi possível revogar o dispositivo.').detail)
      return
    }
    await carregar()
  }

  return (
    <main>
      <h1>Dispositivos</h1>
      {erro && <p role="alert">{erro}</p>}
      <ul data-testid="sessoes">
        {sessoes.map((s) => (
          <li key={s.id}>
            <span>{s.dispositivo ?? 'Dispositivo'}</span>
            <button type="button" onClick={() => s.id && revogar(s.id)}>
              Revogar
            </button>
          </li>
        ))}
      </ul>
    </main>
  )
}