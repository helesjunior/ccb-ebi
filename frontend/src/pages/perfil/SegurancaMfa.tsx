import { useState } from 'react'
import { ShieldCheckIcon, ShieldPlusIcon } from 'lucide-react'

import { api } from '@/api/client'
import { lerErro } from '@/api/erro'
import { cabecalhos, type Usuario } from '@/api/sessao'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { toast } from 'sonner'

interface SegurancaMfaProps {
  usuario: Usuario
  onAtualizado: (usuario: Usuario) => void
}

interface Inscricao {
  segredo?: string
  uri?: string
}

/**
 * Cartão "Segurança": habilita ou renova o MFA (TOTP). Renovar sobrescreve o
 * segredo, revoga o fator antigo e invalida os códigos de recuperação antigos,
 * por isso exige confirmação explícita.
 */
export function SegurancaMfa({ usuario, onAtualizado }: SegurancaMfaProps) {
  const [inscricao, setInscricao] = useState<Inscricao | null>(null)
  const [codigo, setCodigo] = useState('')
  const [codigos, setCodigos] = useState<string[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)
  const [confirmarRenovacao, setConfirmarRenovacao] = useState(false)

  const ativo = usuario.mfa_ativo === true

  function reiniciar() {
    setInscricao(null)
    setCodigo('')
    setCodigos([])
    setErro(null)
  }

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

  async function confirmar() {
    setErro(null)
    setEnviando(true)
    const { error } = await api.POST('/me/mfa/totp/confirm', {
      headers: cabecalhos(),
      body: { codigo },
    })
    if (error) {
      setEnviando(false)
      setErro(lerErro(error, 'Código inválido.').detail)
      return
    }
    await gerarCodigos()
    setEnviando(false)
  }

  async function gerarCodigos() {
    const { data, error } = await api.POST('/me/mfa/recovery-codes', { headers: cabecalhos() })
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível gerar os códigos de recuperação.').detail)
      return
    }
    setCodigos(data.codigos ?? [])
    // Recarrega o perfil para refletir `mfa_ativo`.
    const { data: atualizado } = await api.GET('/me', { headers: cabecalhos() })
    if (atualizado) {
      onAtualizado(atualizado)
    }
    toast.success('MFA atualizado.')
  }

  function renovar() {
    setConfirmarRenovacao(false)
    reiniciar()
    void inscrever()
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          Segurança
          {ativo ? (
            <Badge variant="secondary">Ativo</Badge>
          ) : (
            <Badge variant="outline">Inativo</Badge>
          )}
        </CardTitle>
        <CardDescription>
          Autenticação em duas etapas por aplicativo autenticador (TOTP).
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {!inscricao && (
          <p className="text-sm text-muted-foreground">
            {ativo
              ? 'O MFA está ativo nesta conta.'
              : 'Proteja sua conta habilitando a autenticação em duas etapas.'}
          </p>
        )}

        {inscricao && (
          <div className="flex flex-col gap-3">
            <p className="text-sm text-muted-foreground">
              Adicione o segredo ao seu aplicativo autenticador e informe o código gerado.
            </p>
            <p className="text-sm">
              Segredo: <code data-testid="totp-segredo">{inscricao.segredo}</code>
            </p>
            <p className="truncate text-sm text-muted-foreground">
              URI: <code>{inscricao.uri}</code>
            </p>
            <div className="flex flex-col gap-2">
              <Label htmlFor="codigo-mfa">Código do aplicativo</Label>
              <Input
                id="codigo-mfa"
                inputMode="numeric"
                value={codigo}
                onChange={(e) => setCodigo(e.target.value)}
                autoComplete="one-time-code"
              />
            </div>
            <div className="flex gap-2">
              <Button type="button" onClick={confirmar} disabled={enviando || !codigo}>
                Confirmar
              </Button>
              <Button type="button" variant="ghost" onClick={reiniciar} disabled={enviando}>
                Cancelar
              </Button>
            </div>
          </div>
        )}

        {codigos.length > 0 && (
          <Alert>
            <AlertDescription>
              <p className="mb-2 font-medium">
                Guarde os códigos de recuperação: eles serão exibidos apenas uma vez.
              </p>
              <ul data-testid="recovery-codes" className="grid grid-cols-2 gap-1">
                {codigos.map((c) => (
                  <li key={c}>
                    <code>{c}</code>
                  </li>
                ))}
              </ul>
            </AlertDescription>
          </Alert>
        )}

        {erro && (
          <Alert variant="destructive">
            <AlertDescription>{erro}</AlertDescription>
          </Alert>
        )}
      </CardContent>
      <CardFooter className="flex gap-2">
        {!inscricao &&
          (ativo ? (
            <Button type="button" variant="outline" onClick={() => setConfirmarRenovacao(true)}>
              <ShieldPlusIcon className="size-4" aria-hidden="true" />
              Renovar MFA
            </Button>
          ) : (
            <Button type="button" onClick={inscrever} disabled={enviando}>
              <ShieldCheckIcon className="size-4" aria-hidden="true" />
              Habilitar MFA
            </Button>
          ))}
      </CardFooter>

      <Dialog open={confirmarRenovacao} onOpenChange={setConfirmarRenovacao}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Renovar MFA?</DialogTitle>
            <DialogDescription>
              Isto invalida seu aplicativo atual e os códigos de recuperação antigos. Você
              precisará configurar o autenticador novamente. Continuar?
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => setConfirmarRenovacao(false)}>
              Cancelar
            </Button>
            <Button type="button" variant="destructive" onClick={renovar}>
              Renovar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  )
}