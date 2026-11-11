import { useCallback, useEffect, useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

type Participante = components['schemas']['Participante']

/**
 * Dependentes: participantes que o usuário representa (autorização por
 * propriedade, CA-04). Só aparecem vínculos `aprovada` e vigentes — o servidor
 * decide, a tela apenas exibe. Nunca depende de território nem da matrícula.
 */
export function Dependentes() {
  const [dependentes, setDependentes] = useState<Participante[]>([])
  const [erro, setErro] = useState<string | null>(null)

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/participants', { headers: cabecalhos() })
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível carregar seus dependentes.').detail)
      return
    }
    setDependentes(data.data ?? [])
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  return (
    <main>
      <h1>Meus dependentes</h1>
      {erro && <p role="alert">{erro}</p>}
      {dependentes.length === 0 && !erro ? (
        <p data-testid="sem-dependentes">Nenhum dependente vinculado.</p>
      ) : (
        <ul data-testid="dependentes">
          {dependentes.map((p) => (
            <li key={p.id}>
              {p.nome} — {p.data_nascimento} — {p.matricula}
            </li>
          ))}
        </ul>
      )}
    </main>
  )
}