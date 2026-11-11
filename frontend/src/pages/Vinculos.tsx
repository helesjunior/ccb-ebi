import { useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

type Vinculo = components['schemas']['ResponsavelParticipante']
type Decisao = components['schemas']['DecisaoVinculoInput']['decisao']

const DECISOES: { valor: Decisao; rotulo: string }[] = [
  { valor: 'aprovar', rotulo: 'Aprovar' },
  { valor: 'recusar', rotulo: 'Recusar' },
  { valor: 'pedir_documento', rotulo: 'Pedir documento' },
  { valor: 'revogar', rotulo: 'Revogar' },
  { valor: 'contestar', rotulo: 'Contestar' },
]

/**
 * Vínculos: pedido de vínculo do responsável e decisão da coordenadora (RF-03).
 * Um vínculo só libera acesso após `aprovada` (CA-04). Contestar ou revogar
 * encerra o vínculo (`fim`) sem apagar o histórico.
 */
export function Vinculos() {
  const [participanteId, setParticipanteId] = useState('')
  const [vinculoId, setVinculoId] = useState('')
  const [decisao, setDecisao] = useState<Decisao>('aprovar')
  const [motivo, setMotivo] = useState('')

  const [nome, setNome] = useState('')
  const [tipoRelacao, setTipoRelacao] = useState('responsavel')
  const [telefone, setTelefone] = useState('')

  const [vinculo, setVinculo] = useState<Vinculo | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [ocupado, setOcupado] = useState(false)

  async function solicitar() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.POST('/participants/{id}/guardians', {
      params: { path: { id: participanteId } },
      headers: cabecalhos(),
      body: { nome, tipo_relacao: tipoRelacao, telefone: telefone || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível solicitar o vínculo.').detail)
      return
    }
    setVinculo(data)
    setNome('')
    setTelefone('')
  }

  async function decidir() {
    setErro(null)
    setOcupado(true)
    const { data, error } = await api.PATCH('/guardian-requests/{id}', {
      params: { path: { id: vinculoId } },
      headers: cabecalhos(),
      body: { decisao, motivo: motivo || undefined },
    })
    setOcupado(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível decidir o vínculo.').detail)
      return
    }
    setVinculo(data)
    setMotivo('')
  }

  return (
    <main>
      <h1>Vínculos de responsável</h1>

      <h2>Solicitar vínculo</h2>
      <p>
        <label htmlFor="vinculo-participante">ID do participante</label>
        <input
          id="vinculo-participante"
          value={participanteId}
          onChange={(e) => setParticipanteId(e.target.value)}
          required
        />
      </p>
      <p>
        <label htmlFor="vinculo-nome">Seu nome</label>
        <input id="vinculo-nome" value={nome} onChange={(e) => setNome(e.target.value)} required />
      </p>
      <p>
        <label htmlFor="vinculo-relacao">Tipo de relação</label>
        <input
          id="vinculo-relacao"
          value={tipoRelacao}
          onChange={(e) => setTipoRelacao(e.target.value)}
          required
        />
      </p>
      <p>
        <label htmlFor="vinculo-telefone">Telefone (opcional)</label>
        <input id="vinculo-telefone" value={telefone} onChange={(e) => setTelefone(e.target.value)} />
      </p>
      <button
        type="button"
        onClick={solicitar}
        disabled={ocupado || !participanteId || !nome || !tipoRelacao}
      >
        Solicitar
      </button>

      <h2>Decidir pedido</h2>
      <p>
        <label htmlFor="vinculo-id">ID do vínculo</label>
        <input id="vinculo-id" value={vinculoId} onChange={(e) => setVinculoId(e.target.value)} required />
      </p>
      <p>
        <label htmlFor="vinculo-decisao">Decisão</label>
        <select
          id="vinculo-decisao"
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
        <label htmlFor="vinculo-motivo">Motivo (opcional)</label>
        <input id="vinculo-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
      </p>
      <button type="button" onClick={decidir} disabled={ocupado || !vinculoId}>
        Decidir
      </button>

      {erro && <p role="alert">{erro}</p>}
      {vinculo && (
        <p role="status" data-testid="vinculo-situacao">
          Vínculo {vinculo.id}: {vinculo.validacao} {vinculo.fim ? `(encerrado em ${vinculo.fim})` : ''}
        </p>
      )}
    </main>
  )
}