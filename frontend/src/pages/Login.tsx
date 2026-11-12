import { useState, type FormEvent } from 'react'
import { KeyRoundIcon, ShieldCheckIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import type { components } from '../api/schema'
import type { Usuario } from '../api/sessao'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

/** Resposta 200 do login: desafio de MFA (sem sessão plena). */
type LoginDesafio = components['schemas']['LoginResponse']

interface LoginProps {
  onEntrar: (token: string, usuario: Usuario) => void
}

/** Moldura centrada reusada pelos três estados do login. */
function MolduraLogin({ children }: { children: React.ReactNode }) {
  return (
    <main className="flex min-h-svh items-center justify-center bg-background p-4 text-foreground">
      <div className="w-full max-w-sm">{children}</div>
    </main>
  )
}

/** Faixa de erro acessível (anunciada por leitores de tela). */
function ErroAlert({ mensagem }: { mensagem: string | null }) {
  if (!mensagem) return null

  return (
    <Alert variant="destructive" aria-live="polite">
      <AlertDescription>{mensagem}</AlertDescription>
    </Alert>
  )
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
        <MolduraLogin>
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShieldCheckIcon className="size-5" aria-hidden="true" />
                Configurar verificação em duas etapas
              </CardTitle>
              <CardDescription>
                Sua conta exige um segundo fator. Configure um aplicativo autenticador
                (Google Authenticator, Authy, 1Password…) para continuar.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ErroAlert mensagem={erro} />
            </CardContent>
            <CardFooter>
              <Button type="button" onClick={iniciarInscricao} disabled={enviando} className="w-full">
                {enviando ? 'Gerando…' : 'Gerar chave'}
              </Button>
            </CardFooter>
          </Card>
        </MolduraLogin>
      )
    }

    if (inscricaoPendente && segredo) {
      return (
        <MolduraLogin>
          <Card>
            <CardHeader>
              <CardTitle>Configurar verificação em duas etapas</CardTitle>
              <CardDescription>
                Adicione a chave abaixo ao seu aplicativo autenticador.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <div className="flex flex-col gap-1">
                <Label>Chave</Label>
                <code className="break-all rounded-md bg-muted px-3 py-2 text-sm">{segredo}</code>
              </div>
              {uri && (
                <p className="break-all text-xs text-muted-foreground">Ou use o link otpauth: {uri}</p>
              )}
              <form onSubmit={confirmarInscricao} noValidate className="flex flex-col gap-3">
                <div className="flex flex-col gap-2">
                  <Label htmlFor="codigoInscricao">Código gerado pelo aplicativo</Label>
                  <Input
                    id="codigoInscricao"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    value={codigo}
                    onChange={(e) => setCodigo(e.target.value)}
                    required
                  />
                </div>
                <ErroAlert mensagem={erro} />
                <Button type="submit" disabled={enviando} className="w-full">
                  {enviando ? 'Confirmando…' : 'Confirmar e entrar'}
                </Button>
              </form>
            </CardContent>
          </Card>
        </MolduraLogin>
      )
    }

    return (
      <MolduraLogin>
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <KeyRoundIcon className="size-5" aria-hidden="true" />
              Confirmar acesso
            </CardTitle>
            <CardDescription>
              Informe o código do seu aplicativo autenticador ou um código de recuperação.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={enviarCodigo} noValidate className="flex flex-col gap-3">
              <div className="flex flex-col gap-2">
                <Label htmlFor="codigo">Código</Label>
                <Input
                  id="codigo"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  autoFocus
                  value={codigo}
                  onChange={(e) => setCodigo(e.target.value)}
                  required
                />
              </div>
              <ErroAlert mensagem={erro} />
              <Button type="submit" disabled={enviando} className="w-full">
                {enviando ? 'Validando…' : 'Validar'}
              </Button>
            </form>
          </CardContent>
        </Card>
      </MolduraLogin>
    )
  }

  return (
    <MolduraLogin>
      <Card>
        <CardHeader>
          <CardTitle>Entrar</CardTitle>
          <CardDescription>Acesse a plataforma EBI.</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={enviarCredenciais} noValidate className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="email">E-mail</Label>
              <Input
                id="email"
                type="email"
                autoComplete="username"
                autoFocus
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="senha">Senha</Label>
              <Input
                id="senha"
                type="password"
                autoComplete="current-password"
                value={senha}
                onChange={(e) => setSenha(e.target.value)}
                required
              />
            </div>
            <ErroAlert mensagem={erro} />
            <Button type="submit" disabled={enviando} className="w-full">
              {enviando ? 'Entrando…' : 'Entrar'}
            </Button>
          </form>
        </CardContent>
      </Card>
    </MolduraLogin>
  )
}