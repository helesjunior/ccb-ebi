import { useState, type FormEvent } from 'react'

import { api } from '@/api/client'
import { lerErro } from '@/api/erro'
import { cabecalhos } from '@/api/sessao'
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
import { toast } from 'sonner'

/**
 * Cartão "Senha": troca a senha do próprio usuário (`POST /me/password`).
 * A sessão atual permanece válida; as demais são revogadas no servidor.
 */
export function SenhaForm() {
  const [senhaAtual, setSenhaAtual] = useState('')
  const [nova, setNova] = useState('')
  const [confirmacao, setConfirmacao] = useState('')
  const [erroAtual, setErroAtual] = useState<string | null>(null)
  const [erroGeral, setErroGeral] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  function limpar() {
    setSenhaAtual('')
    setNova('')
    setConfirmacao('')
  }

  async function submeter(evento: FormEvent) {
    evento.preventDefault()
    setErroAtual(null)
    setErroGeral(null)

    if (nova !== confirmacao) {
      setErroGeral('A confirmação não confere com a nova senha.')
      return
    }

    setEnviando(true)
    const { error } = await api.POST('/me/password', {
      headers: cabecalhos(),
      body: {
        senha_atual: senhaAtual,
        nova_senha: nova,
        nova_senha_confirmation: confirmacao,
      },
    })
    setEnviando(false)

    if (error) {
      const problema = lerErro(error, 'Não foi possível alterar a senha.')
      if (problema.code === 'senha_atual_invalida') {
        setErroAtual(problema.detail)
      } else {
        setErroGeral(problema.detail)
      }
      return
    }

    limpar()
    toast.success('Senha alterada.')
  }

  return (
    <Card>
      <form onSubmit={submeter} noValidate>
        <CardHeader>
          <CardTitle>Senha</CardTitle>
          <CardDescription>
            Mínimo de 12 caracteres. As outras sessões serão encerradas.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="senha-atual">Senha atual</Label>
            <Input
              id="senha-atual"
              type="password"
              value={senhaAtual}
              onChange={(e) => setSenhaAtual(e.target.value)}
              autoComplete="current-password"
              aria-invalid={erroAtual ? true : undefined}
              aria-describedby={erroAtual ? 'senha-atual-erro' : undefined}
              required
            />
            {erroAtual && (
              <p id="senha-atual-erro" role="alert" className="text-sm text-destructive">
                {erroAtual}
              </p>
            )}
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="senha-nova">Nova senha</Label>
            <Input
              id="senha-nova"
              type="password"
              value={nova}
              onChange={(e) => setNova(e.target.value)}
              autoComplete="new-password"
              minLength={12}
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="senha-confirmacao">Confirmar nova senha</Label>
            <Input
              id="senha-confirmacao"
              type="password"
              value={confirmacao}
              onChange={(e) => setConfirmacao(e.target.value)}
              autoComplete="new-password"
              minLength={12}
              required
            />
          </div>
          {erroGeral && (
            <Alert variant="destructive">
              <AlertDescription>{erroGeral}</AlertDescription>
            </Alert>
          )}
        </CardContent>
        <CardFooter>
          <Button type="submit" disabled={enviando}>
            Alterar senha
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}