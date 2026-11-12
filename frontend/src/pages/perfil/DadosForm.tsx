import { useState, type FormEvent } from 'react'

import { api } from '@/api/client'
import { lerErro } from '@/api/erro'
import { cabecalhos, type Usuario } from '@/api/sessao'
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

interface DadosFormProps {
  usuario: Usuario
  onAtualizado: (usuario: Usuario) => void
}

/** Cartão "Dados": edita nome e e-mail do próprio usuário (`PATCH /me`). */
export function DadosForm({ usuario, onAtualizado }: DadosFormProps) {
  const [nome, setNome] = useState(usuario.nome ?? '')
  const [email, setEmail] = useState(usuario.email ?? '')
  const [erroEmail, setErroEmail] = useState<string | null>(null)
  const [erroGeral, setErroGeral] = useState<string | null>(null)
  const [avisoVerificacao, setAvisoVerificacao] = useState(false)
  const [enviando, setEnviando] = useState(false)

  async function submeter(evento: FormEvent) {
    evento.preventDefault()
    setErroEmail(null)
    setErroGeral(null)
    setAvisoVerificacao(false)
    setEnviando(true)

    const { data, error } = await api.PATCH('/me', {
      headers: cabecalhos(),
      body: { nome, email },
    })
    setEnviando(false)

    if (error || !data) {
      const problema = lerErro(error, 'Não foi possível salvar os dados.')
      if (problema.code === 'email_em_uso') {
        setErroEmail(problema.detail)
      } else {
        setErroGeral(problema.detail)
      }
      return
    }

    // O e-mail só perde a verificação quando muda de fato.
    const mudouEmail = (usuario.email ?? '') !== (data.email ?? '')
    onAtualizado(data)
    toast.success('Perfil atualizado.')
    if (mudouEmail) {
      setAvisoVerificacao(true)
    }
  }

  const alterado = nome !== (usuario.nome ?? '') || email !== (usuario.email ?? '')

  return (
    <Card>
      <form onSubmit={submeter} noValidate>
        <CardHeader>
          <CardTitle>Dados</CardTitle>
          <CardDescription>Seu nome e e-mail de acesso.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="perfil-nome">Nome</Label>
            <Input
              id="perfil-nome"
              value={nome}
              onChange={(e) => setNome(e.target.value)}
              autoComplete="name"
              required
            />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="perfil-email">E-mail</Label>
            <Input
              id="perfil-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              aria-invalid={erroEmail ? true : undefined}
              aria-describedby={erroEmail ? 'perfil-email-erro' : undefined}
              required
            />
            {erroEmail && (
              <p id="perfil-email-erro" role="alert" className="text-sm text-destructive">
                {erroEmail}
              </p>
            )}
          </div>
          {avisoVerificacao && (
            <Alert>
              <AlertDescription>
                Seu e-mail foi alterado. A verificação do novo endereço está pendente.
              </AlertDescription>
            </Alert>
          )}
          {erroGeral && (
            <Alert variant="destructive">
              <AlertDescription>{erroGeral}</AlertDescription>
            </Alert>
          )}
        </CardContent>
        <CardFooter>
          <Button type="submit" disabled={enviando || !alterado}>
            Salvar
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}