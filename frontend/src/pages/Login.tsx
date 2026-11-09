import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import type { components } from '../api/schema'
import type { Usuario } from '../api/sessao'

/** Resposta 200 do login: desafio de MFA (sem sessão plena). */
type LoginDesafio = components['schemas']['LoginResponse']

interface LoginProps {
  onEntrar: (token: string, usuario: Usuario) => void
}

/**
 * Login em dois passos. Perfis com MFA obrigatório recebem `mfa_required` +
 * `token_desafio`; a sessão plena só é emitida após validar o segundo fator
 * em `/auth/mfa/verify` (CA-15: o token de desafio não abre a área operacional).
 *
 * Quando o perfil ainda não tem fator inscrito (`mfa_inscricao_pendente`), o
 * mesmo token de desafio inscreve o TOTP (`/me/mfa/totp` + `/confirm`) antes de
 * verificar — assim um usuário novo conclui o login sem travar.
 */
export function Login({ onEntrar }: LoginProps) {
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [desafio, setDesafio] = useState<string | null>(null)
  const [inscricaoPendente, setInscricaoPendente] = useState(false)
  const [segredo, setSegredo] = useState<string | null>(null)
  const [uri, setUri] = useState<string | null>(null)
  const [codigo, setCodigo] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function enviarCredenciais(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const { data, error, response } = await api.POST('/auth/login', {
      body: { email, password: senha },
    })

    setEnviando(false)

    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível entrar. Confira o e-mail e a senha.').detail)
      return
    }

    // 200 = desafio de MFA (sessão plena bloqueada); 201 = sessão emitida.
    if (response.status === 200) {
      const desafioResp = data as LoginDesafio
      if (desafioResp.token_desafio) {
        setDesafio(desafioResp.token_desafio)
        setInscricaoPendente(desafioResp.mfa_inscricao_pendente === true)
      }
      return
    }

    if (data.token && data.usuario) {
      onEntrar(data.token, data.usuario)
    }
  }

  /** Busca o segredo/URI para inscrever o TOTP usando o token de desafio. */
  async function iniciarInscricao() {
    setErro(null)
    setEnviando(true)

    const { data, error } = await api.POST('/me/mfa/totp', {
      headers: { Authorization: `Bearer ${desafio ?? ''}` },
    })

    setEnviando(false)

    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível iniciar a inscrição do aplicativo autenticador.').detail)
      return
    }

    setSegredo(data.segredo ?? null)
    setUri(data.uri ?? null)
  }

  /**
   * Confirma a inscrição com o código gerado pelo app e, em seguida (mesmo
   * passo), conclui o desafio no `/auth/mfa/verify` com o mesmo código.
   */
  async function confirmarInscricao(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)

    const confirmar = await api.POST('/me/mfa/totp/confirm', {
      headers: { Authorization: `Bearer ${desafio ?? ''}` },
      body: { codigo },
    })

    if (confirmar.error || !confirmar.data) {
      setEnviando(false)
      setErro(lerErro(confirmar.error, 'Código inválido. Confira o horário do aparelho e tente novamente.').detail)
      return
    }

    await concluirVerificacao()
  }

  async function enviarCodigo(evento: FormEvent) {
    evento.preventDefault()
    await concluirVerificacao()
  }

  /** Valida o segundo fator e emite a sessão plena. */
  async function concluirVerificacao() {
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
    // Usuário sem fator: primeiro inscreve o TOTP (mostra o segredo/URI), depois
    // confirma o código. O código confirmado conclui o desafio no mesmo passo.
    if (inscricaoPendente && !segredo) {
      return (
        <main>
          <h1>Configurar verificação em duas etapas</h1>
          <p>
            Sua conta exige um segundo fator. Configure um aplicativo autenticador
            (Google Authenticator, Authy, 1Password…) para continuar.
          </p>
          {erro && <p role="alert">{erro}</p>}
          <button type="button" onClick={iniciarInscricao} disabled={enviando}>
            {enviando ? 'Gerando…' : 'Gerar chave'}
          </button>
        </main>
      )
    }

    if (inscricaoPendente && segredo) {
      return (
        <main>
          <h1>Configurar verificação em duas etapas</h1>
          <p>Adicione a chave abaixo ao seu aplicativo autenticador:</p>
          <p>
            <code>{segredo}</code>
          </p>
          {uri && (
            <p>
              <small>Ou use o link otpauth: {uri}</small>
            </p>
          )}
          <form onSubmit={confirmarInscricao} noValidate>
            <p>
              <label htmlFor="codigoInscricao">Código gerado pelo aplicativo</label>
              <input
                id="codigoInscricao"
                inputMode="numeric"
                autoComplete="one-time-code"
                value={codigo}
                onChange={(e) => setCodigo(e.target.value)}
                required
              />
            </p>
            {erro && <p role="alert">{erro}</p>}
            <button type="submit" disabled={enviando}>
              {enviando ? 'Confirmando…' : 'Confirmar e entrar'}
            </button>
          </form>
        </main>
      )
    }

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