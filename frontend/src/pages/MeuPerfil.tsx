import { useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type Usuario } from '../api/sessao'

interface MeuPerfilProps {
  usuario: Usuario
}

/**
 * Meu perfil: inscreve e confirma o TOTP e gera códigos de recuperação.
 * A inscrição devolve segredo + URI otpauth; a confirmação valida um código.
 */
export function MeuPerfil({ usuario }: MeuPerfilProps) {
  const [inscricao, setInscricao] = useState<{ segredo?: string; uri?: string } | null>(null)
  const [codigo, setCodigo] = useState('')
  const [codigos, setCodigos] = useState<string[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  async function inscrever() {
    setErro(null)
    setEnviando(true)
    const { data, error } = await api.POST('/me/mfa/totp', { headers: cabecalhos() })
    setEnviando(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível iniciar a inscrição do MFA.').detail)
      return
    }
    setInscricao(data)
  }

  async function confirmar(evento: FormEvent) {
    evento.preventDefault()
    setErro(null)
    setEnviando(true)
    const { error } = await api.POST('/me/mfa/totp/confirm', {
      headers: cabecalhos(),
      body: { codigo },
    })
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Código inválido.').detail)
      return
    }
    await gerarCodigos()
  }

  async function gerarCodigos() {
    setErro(null)
    const { data, error } = await api.POST('/me/mfa/recovery-codes', { headers: cabecalhos() })
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível gerar os códigos de recuperação.').detail)
      return
    }
    setCodigos(data.codigos ?? [])
  }

  return (
    <main>
      <h1>Meu perfil</h1>
      <p data-testid="perfil-nome">{usuario.nome}</p>
      <p>{usuario.email}</p>
      <p>Papéis: {(usuario.papeis ?? []).join(', ') || '—'}</p>

      <h2>Autenticação em duas etapas</h2>
      {!inscricao && (
        <button type="button" onClick={inscrever} disabled={enviando}>
          Iniciar inscrição do MFA
        </button>
      )}
      {inscricao && (
        <>
          <p>
            Segredo: <code data-testid="totp-segredo">{inscricao.segredo}</code>
          </p>
          <p>
            URI: <code>{inscricao.uri}</code>
          </p>
          <form onSubmit={confirmar} noValidate>
            <p>
              <label htmlFor="codigo-mfa">Código do aplicativo</label>
              <input
                id="codigo-mfa"
                inputMode="numeric"
                value={codigo}
                onChange={(e) => setCodigo(e.target.value)}
                required
              />
            </p>
            <button type="submit" disabled={enviando}>
              Confirmar
            </button>
          </form>
        </>
      )}

      <button type="button" onClick={gerarCodigos}>
        Gerar códigos de recuperação
      </button>
      {codigos.length > 0 && (
        <ul data-testid="recovery-codes">
          {codigos.map((c) => (
            <li key={c}>
              <code>{c}</code>
            </li>
          ))}
        </ul>
      )}
      {erro && <p role="alert">{erro}</p>}
    </main>
  )
}