import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'

interface AceitarConviteProps {
  conviteId: string
  token: string
}

/**
 * Aceite de convite sem login: o token secreto do convite é a credencial.
 * Cria a conta já vinculada (uso único). Replay/token inválido devolve 410.
 */
export function AceitarConvite({ conviteId, token }: AceitarConviteProps) {
  const [nome, setNome] = useState('')
  const [senha, setSenha] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [concluido, setConcluido] = useState(false)

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { error } = await api.POST('/invitations/{id}/accept', {
      params: { path: { id: conviteId } },
      body: { token, nome, password: senha },
    })

    setEnviando(false)

    if (error) {
      const problema = lerErro(error, 'Não foi possível aceitar o convite.')
      setErro(
        problema.code === 'chefe_ja_definida'
          ? 'Este EBI já tem uma coordenação chefe vigente. Peça um novo convite ao administrador.'
          : problema.detail,
      )
      return
    }

    setConcluido(true)
  }

  if (concluido) {
    return (
      <main>
        <h1>Convite aceito</h1>
        <p role="status">Sua conta foi criada e vinculada. Você já pode entrar.</p>
      </main>
    )
  }

  return (
    <main>
      <h1>Aceitar convite</h1>
      <form onSubmit={enviar} noValidate>
        <p>
          <label htmlFor="nome">Nome completo</label>
          <input id="nome" value={nome} onChange={(e) => setNome(e.target.value)} required />
        </p>
        <p>
          <label htmlFor="senha">Senha</label>
          <input
            id="senha"
            type="password"
            autoComplete="new-password"
            minLength={12}
            value={senha}
            onChange={(e) => setSenha(e.target.value)}
            required
          />
        </p>
        {erro && <p role="alert">{erro}</p>}
        <button type="submit" disabled={enviando}>
          {enviando ? 'Enviando…' : 'Aceitar convite'}
        </button>
      </form>
    </main>
  )
}