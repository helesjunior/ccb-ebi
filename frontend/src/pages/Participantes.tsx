import { useState } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import {
  FormularioParticipante,
  type Participante,
} from '../components/FormularioParticipante'

/**
 * Participantes: cadastra um participante e busca por nome (dedup, CA-27).
 *
 * A leitura identificável exige uma **finalidade declarada** (matriz `*` da
 * leitura): ela vai no parâmetro `finalidade` e é registrada na auditoria do
 * servidor. Sem ela, a API recusa a busca (422).
 */
export function Participantes() {
  const [criado, setCriado] = useState<Participante | null>(null)
  const [termo, setTermo] = useState('')
  const [finalidade, setFinalidade] = useState('confirmacao_cadastro')
  const [resultados, setResultados] = useState<Participante[]>([])
  const [erroBusca, setErroBusca] = useState<string | null>(null)
  const [buscando, setBuscando] = useState(false)

  async function buscar() {
    setErroBusca(null)
    setBuscando(true)
    const { data, error } = await api.GET('/participants', {
      params: { query: { q: termo, finalidade } },
      headers: cabecalhos(),
    })
    setBuscando(false)
    if (error || !data) {
      setErroBusca(lerErro(error, 'Não foi possível buscar participantes.').detail)
      return
    }
    setResultados(data.data ?? [])
  }

  return (
    <main>
      <h1>Participantes</h1>

      <h2>Cadastrar</h2>
      <FormularioParticipante onCriado={setCriado} />
      {criado && (
        <p role="status" data-testid="participante-criado">
          Cadastrado: {criado.nome} — matrícula <code>{criado.matricula}</code>
        </p>
      )}

      <h2>Buscar (dedup)</h2>
      <p>
        <label htmlFor="busca-termo">Nome</label>
        <input
          id="busca-termo"
          value={termo}
          onChange={(e) => setTermo(e.target.value)}
        />
      </p>
      <p>
        <label htmlFor="busca-finalidade">Finalidade da leitura</label>
        <input
          id="busca-finalidade"
          value={finalidade}
          onChange={(e) => setFinalidade(e.target.value)}
          required
        />
      </p>
      <button type="button" onClick={buscar} disabled={buscando || !termo || !finalidade}>
        {buscando ? 'Buscando…' : 'Buscar'}
      </button>
      {erroBusca && <p role="alert">{erroBusca}</p>}
      <ul data-testid="resultados">
        {resultados.map((p) => (
          <li key={p.id}>
            {p.nome} — {p.data_nascimento} — {p.matricula}
          </li>
        ))}
      </ul>
    </main>
  )
}