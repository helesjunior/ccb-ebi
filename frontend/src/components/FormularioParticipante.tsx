import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

export type Participante = components['schemas']['Participante']

export interface DadosParticipante {
  nome: string
  nome_social: string
  data_nascimento: string
  igreja_referencia_id: string
}

const VAZIO: DadosParticipante = {
  nome: '',
  nome_social: '',
  data_nascimento: '',
  igreja_referencia_id: '',
}

/**
 * Formulário de cadastro do participante pelo responsável (RF-02). Chama
 * `POST /participants` e entrega o participante criado ao pai. Os dados de
 * consentimento (termo) são registrados na tela de Privacidade; aqui só o
 * cadastro identificável, que é auditado no servidor.
 */
export function FormularioParticipante({
  onCriado,
}: {
  onCriado: (p: Participante) => void
}) {
  const [dados, setDados] = useState<DadosParticipante>(VAZIO)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  function campo<K extends keyof DadosParticipante>(chave: K) {
    return (evento: { target: { value: string } }) =>
      setDados((atual) => ({ ...atual, [chave]: evento.target.value }))
  }

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { data, error } = await api.POST('/participants', {
      headers: cabecalhos(),
      body: {
        nome: dados.nome,
        nome_social: dados.nome_social || undefined,
        data_nascimento: dados.data_nascimento,
        igreja_referencia_id: dados.igreja_referencia_id || undefined,
      },
    })

    setEnviando(false)

    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível cadastrar o participante.').detail)
      return
    }

    setDados(VAZIO)
    onCriado(data)
  }

  return (
    <form onSubmit={enviar} noValidate>
      <p>
        <label htmlFor="participante-nome">Nome completo</label>
        <input
          id="participante-nome"
          value={dados.nome}
          onChange={campo('nome')}
          required
        />
      </p>
      <p>
        <label htmlFor="participante-nome-social">Nome social (opcional)</label>
        <input
          id="participante-nome-social"
          value={dados.nome_social}
          onChange={campo('nome_social')}
        />
      </p>
      <p>
        <label htmlFor="participante-nascimento">Data de nascimento</label>
        <input
          id="participante-nascimento"
          type="date"
          value={dados.data_nascimento}
          onChange={campo('data_nascimento')}
          required
        />
      </p>
      <p>
        <label htmlFor="participante-igreja">Igreja de referência (opcional)</label>
        <input
          id="participante-igreja"
          value={dados.igreja_referencia_id}
          onChange={campo('igreja_referencia_id')}
        />
      </p>
      {erro && <p role="alert">{erro}</p>}
      <button type="submit" disabled={enviando}>
        {enviando ? 'Cadastrando…' : 'Cadastrar participante'}
      </button>
    </form>
  )
}