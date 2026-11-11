import { useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

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

  const finalidadeValida = finalidade.trim() !== ''

  async function buscar() {
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
    await buscar()
  }

  return (
    <main>
      <h1>Mesclagem de duplicados</h1>

      <h2>Candidatos</h2>
      <p>
        <label htmlFor="mescla-termo">Nome</label>
        <input id="mescla-termo" value={termo} onChange={(e) => setTermo(e.target.value)} />
      </p>
      <p>
        <label htmlFor="mescla-nascimento">Data de nascimento (opcional)</label>
        <input
          id="mescla-nascimento"
          type="date"
          value={dataNascimento}
          onChange={(e) => setDataNascimento(e.target.value)}
        />
      </p>
      <p>
        <label htmlFor="mescla-finalidade">Finalidade da leitura</label>
        <input
          id="mescla-finalidade"
          value={finalidade}
          onChange={(e) => setFinalidade(e.target.value)}
          required
        />
      </p>
      <button type="button" onClick={buscar} disabled={ocupado || !termo}>
        Buscar
      </button>

      <ul data-testid="candidatos">
        {candidatos.map((p) => (
          <li key={p.id}>
            {p.nome} — {p.data_nascimento} — {p.matricula}{' '}
            <button type="button" onClick={() => setPrincipalId(p.id)}>
              principal
            </button>
            <button type="button" onClick={() => setDuplicadoId(p.id)}>
              duplicado
            </button>
          </li>
        ))}
      </ul>

      <h2>Mesclar</h2>
      <p data-testid="escolhidos">
        Principal: <code>{principalId || '—'}</code> · Duplicado:{' '}
        <code>{duplicadoId || '—'}</code>
      </p>
      {erro && <p role="alert">{erro}</p>}
      {mensagem && <p role="status">{mensagem}</p>}
      <button
        type="button"
        onClick={mesclar}
        disabled={ocupado || !principalId || !duplicadoId || principalId === duplicadoId}
      >
        Mesclar
      </button>
      <button type="button" onClick={desfazer} disabled={ocupado || !principalId}>
        Desfazer última mesclagem
      </button>
    </main>
  )
}