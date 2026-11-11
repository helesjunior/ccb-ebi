import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

type Participante = components['schemas']['Participante']

/**
 * Cadastro assistido (FL-01, CA-16): a coordenadora cadastra presencialmente e
 * envia a **evidência de aceite obrigatória** (arquivo). O arquivo entra pelo
 * pipeline de storage (`multipart/form-data`): o servidor guarda em quarentena,
 * valida o tipo real, roda o antivírus e só então promove para o bucket cifrado.
 */
export function CadastroAssistido() {
  const [nome, setNome] = useState('')
  const [dataNascimento, setDataNascimento] = useState('')
  const [responsavelNome, setResponsavelNome] = useState('')
  const [tipoRelacao, setTipoRelacao] = useState('mae')
  const [evidencia, setEvidencia] = useState<File | null>(null)
  const [criado, setCriado] = useState<Participante | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)

    if (!evidencia) {
      setErro('A evidência de aceite é obrigatória.')
      return
    }

    setEnviando(true)

    const formulario = new FormData()
    formulario.append('nome', nome)
    formulario.append('data_nascimento', dataNascimento)
    formulario.append('responsavel_nome', responsavelNome)
    formulario.append('tipo_relacao', tipoRelacao)
    formulario.append('evidencia', evidencia)

    const { data, error } = await api.POST('/participants/assisted', {
      headers: cabecalhos(),
      body: formulario as never,
    })

    setEnviando(false)

    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível concluir o cadastro assistido.').detail)
      return
    }

    setCriado(data)
    setNome('')
    setDataNascimento('')
    setResponsavelNome('')
    setEvidencia(null)
  }

  return (
    <main>
      <h1>Cadastro assistido</h1>
      <form onSubmit={enviar} noValidate>
        <p>
          <label htmlFor="assistido-nome">Nome completo</label>
          <input id="assistido-nome" value={nome} onChange={(e) => setNome(e.target.value)} required />
        </p>
        <p>
          <label htmlFor="assistido-nascimento">Data de nascimento</label>
          <input
            id="assistido-nascimento"
            type="date"
            value={dataNascimento}
            onChange={(e) => setDataNascimento(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="assistido-responsavel">Nome do responsável</label>
          <input
            id="assistido-responsavel"
            value={responsavelNome}
            onChange={(e) => setResponsavelNome(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="assistido-relacao">Tipo de relação</label>
          <input
            id="assistido-relacao"
            value={tipoRelacao}
            onChange={(e) => setTipoRelacao(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="assistido-evidencia">Evidência de aceite (PDF/JPEG/PNG)</label>
          <input
            id="assistido-evidencia"
            type="file"
            accept="application/pdf,image/jpeg,image/png"
            onChange={(e) => setEvidencia(e.target.files?.[0] ?? null)}
            required
          />
        </p>
        {erro && <p role="alert">{erro}</p>}
        <button type="submit" disabled={enviando}>
          {enviando ? 'Enviando…' : 'Cadastrar com evidência'}
        </button>
      </form>
      {criado && (
        <p role="status" data-testid="assistido-criado">
          Cadastrado: {criado.nome} — matrícula <code>{criado.matricula}</code>
        </p>
      )}
    </main>
  )
}