import { useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

type Excecao = components['schemas']['ExcecaoEtaria']
type Decisao = components['schemas']['DecisaoExcecaoInput']['decisao']

const DECISOES: { valor: Decisao; rotulo: string }[] = [
  { valor: 'aprovar', rotulo: 'Aprovar' },
  { valor: 'recusar', rotulo: 'Recusar' },
  { valor: 'suspender', rotulo: 'Suspender' },
  { valor: 'revogar', rotulo: 'Revogar' },
]

/**
 * Exceção inclusiva (FL-06, ADR-0012): o responsável solicita, a coordenadora
 * decide. A exceção libera a presença de quem completou 12 anos, mas **nunca**
 * libera o bloqueio de menores de 3 anos (CA-19). Estados suspensa/revogada/
 * expirada deixam de valer (RN-15).
 */
export function ExcecaoInclusiva() {
  const [participanteId, setParticipanteId] = useState('')
  const [tipoNecessidade, setTipoNecessidade] = useState('neurodivergencia')
  const [apoios, setApoios] = useState('')

  const [excecaoId, setExcecaoId] = useState('')
  const [decisao, setDecisao] = useState<Decisao>('aprovar')
  const [vigenteAte, setVigenteAte] = useState('')
  const [motivo, setMotivo] = useState('')

  const [excecao, setExcecao] = useState<Excecao | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  async function solicitar() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.POST('/participants/{id}/age-exceptions', {
      params: { path: { id: participanteId } },
      headers: cabecalhos(),
      body: { tipo_necessidade: tipoNecessidade, apoios_necessarios: apoios || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível solicitar a exceção.').detail)
      return
    }
    setExcecao(data)
    setApoios('')
  }

  async function decidir() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.PATCH('/age-exceptions/{id}', {
      params: { path: { id: excecaoId } },
      headers: cabecalhos(),
      body: {
        decisao,
        vigente_ate: vigenteAte || undefined,
        motivo: motivo || undefined,
      },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível decidir a exceção.').detail)
      return
    }
    setExcecao(data)
    setMotivo('')
  }

  return (
    <main>
      <h1>Exceção inclusiva</h1>

      <h2>Solicitar (responsável)</h2>
      <p>
        <label htmlFor="excecao-participante">ID do participante</label>
        <input
          id="excecao-participante"
          value={participanteId}
          onChange={(e) => setParticipanteId(e.target.value)}
          required
        />
      </p>
      <p>
        <label htmlFor="excecao-tipo">Tipo de necessidade</label>
        <input
          id="excecao-tipo"
          value={tipoNecessidade}
          onChange={(e) => setTipoNecessidade(e.target.value)}
          required
        />
      </p>
      <p>
        <label htmlFor="excecao-apoios">Apoios necessários (opcional)</label>
        <textarea id="excecao-apoios" value={apoios} onChange={(e) => setApoios(e.target.value)} />
      </p>
      <button
        type="button"
        onClick={solicitar}
        disabled={ocupado || !participanteId || !tipoNecessidade}
      >
        Solicitar exceção
      </button>

      <h2>Decidir (coordenadora)</h2>
      <p>
        <label htmlFor="excecao-id">ID da exceção</label>
        <input id="excecao-id" value={excecaoId} onChange={(e) => setExcecaoId(e.target.value)} required />
      </p>
      <p>
        <label htmlFor="excecao-decisao">Decisão</label>
        <select
          id="excecao-decisao"
          value={decisao}
          onChange={(e) => setDecisao(e.target.value as Decisao)}
        >
          {DECISOES.map((d) => (
            <option key={d.valor} value={d.valor}>
              {d.rotulo}
            </option>
          ))}
        </select>
      </p>
      <p>
        <label htmlFor="excecao-vigencia">Vigente até (opcional)</label>
        <input
          id="excecao-vigencia"
          type="date"
          value={vigenteAte}
          onChange={(e) => setVigenteAte(e.target.value)}
        />
      </p>
      <p>
        <label htmlFor="excecao-motivo">Motivo (opcional)</label>
        <input id="excecao-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
      </p>
      <button type="button" onClick={decidir} disabled={ocupado || !excecaoId}>
        Decidir
      </button>

      {erro && <p role="alert">{erro}</p>}
      {excecao && (
        <p role="status" data-testid="excecao-situacao">
          Exceção {excecao.id}: {excecao.status}
          {excecao.vigente_ate ? ` · vigente até ${excecao.vigente_ate}` : ''}
        </p>
      )}
    </main>
  )
}