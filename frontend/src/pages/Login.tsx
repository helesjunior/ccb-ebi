import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import type { Usuario } from '../api/sessao'

interface LoginProps {
  onEntrar: (token: string, usuario: Usuario) => void
}

/**
 * Login em dois passos. Perfis com MFA obrigatório recebem `mfa_required` +
 * `token_desafio`; a sessão plena só é emitida após validar o segundo fator
 * em `/auth/mfa/verify` (CA-15: o token de desafio não abre a área operacional).
 */
export function Login({ onEntrar }: LoginProps) {
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [desafio, setDesafio] = useState<string | null>(null)
  const [codigo, setCodigo] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function enviarCredenciais(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { data, error } = await api.POST('/auth/login', {
      body: { email, password: senha },
    })

    setEnviando(false)

    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível entrar. Confira o e-mail e a senha.').detail)
      return
    }

    if (data.mfa_required && data.token_desafio) {
      setDesafio(data.token_desafio)
      return
    }

    if (data.token && data.usuario) {
      onEntrar(data.token, data.usuario)
    }
  }

  async function enviarCodigo(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { data, error } = await api.POST('/auth/mfa/verify', {
      headers: { Authorization: `Bearer ${desafio ?? ''}` },
      body: { codigo },
    })

    setEnviando(false)

    if (error || !data || !data.token || !data.usuario) {
      setErro(lerErro(error, 'Código inválido. Tente novamente.').detail)
      return
    }

    onEntrar(data.token, data.usuario)
  }

  if (desafio) {
    return (
      <main>
        <h1>Confirmar acesso</h1>
        <p>Informe o código do seu aplicativo autenticador ou um código de recuperação.</p>
        <form onSubmit={enviarCodigo} noValidate>
          <p>
            <label htmlFor="codigo">Código</label>
            <input
              id="codigo"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={codigo}
              onChange={(e) => setCodigo(e.target.value)}
              required
            />
          </p>
          {erro && <p role="alert">{erro}</p>}
          <button type="submit" disabled={enviando}>
            {enviando ? 'Validando…' : 'Validar'}
          </button>
        </form>
      </main>
    )
  }

  return (
    <main>
      <h1>Entrar</h1>
      <form onSubmit={enviarCredenciais} noValidate>
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