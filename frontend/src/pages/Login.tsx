import { useState, type FormEvent } from 'react'

import { api } from '../api/client'

export interface Ator {
  id: number
  nome: string
  email: string
  papel: string
}

interface LoginProps {
  onEntrar: (ator: Ator) => void
}

export function Login({ onEntrar }: LoginProps) {
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { data, error } = await api.POST('/auth/login', {
      body: { email, password: senha },
    })

    setEnviando(false)

    if (error || !data) {
      setErro('Não foi possível entrar. Confira o e-mail e a senha.')
      return
    }

    sessionStorage.setItem('ebi.token', data.token)
    onEntrar(data.ator as Ator)
  }

  return (
    <main>
      <h1>Entrar</h1>
      <form onSubmit={enviar} noValidate>
        <p>
          <label htmlFor="email">E-mail</label>
          <input
            id="email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </p>
        <p>
          <label htmlFor="senha">Senha</label>
          <input
            id="senha"
            type="password"
            autoComplete="current-password"
            value={senha}
            onChange={(e) => setSenha(e.target.value)}
            required
          />
        </p>
        {erro && <p role="alert">{erro}</p>}
        <button type="submit" disabled={enviando}>
          {enviando ? 'Entrando…' : 'Entrar'}
        </button>
      </form>
    </main>
  )
}