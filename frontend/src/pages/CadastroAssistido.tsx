import { useState, type FormEvent } from 'react'
import { FileUpIcon, UserPlusIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

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
    <section className="mx-auto flex max-w-2xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UserPlusIcon className="size-5" aria-hidden="true" />
            Cadastro assistido
          </CardTitle>
          <CardDescription>
            Cadastro presencial pela coordenação, com evidência de aceite obrigatória.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-nome">Nome completo</Label>
              <Input
                id="assistido-nome"
                value={nome}
                onChange={(e) => setNome(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-nascimento">Data de nascimento</Label>
              <Input
                id="assistido-nascimento"
                type="date"
                value={dataNascimento}
                onChange={(e) => setDataNascimento(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-responsavel">Nome do responsável</Label>
              <Input
                id="assistido-responsavel"
                value={responsavelNome}
                onChange={(e) => setResponsavelNome(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-relacao">Tipo de relação</Label>
              <Input
                id="assistido-relacao"
                value={tipoRelacao}
                onChange={(e) => setTipoRelacao(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-evidencia" className="flex items-center gap-2">
                <FileUpIcon className="size-4" aria-hidden="true" />
                Evidência de aceite (PDF/JPEG/PNG)
              </Label>
              <Input
                id="assistido-evidencia"
                type="file"
                accept="application/pdf,image/jpeg,image/png"
                onChange={(e) => setEvidencia(e.target.files?.[0] ?? null)}
                required
              />
            </div>
            {erro && (
              <Alert variant="destructive">
                <AlertDescription>{erro}</AlertDescription>
              </Alert>
            )}
            <Button type="submit" disabled={enviando} className="self-start">
              {enviando ? 'Enviando…' : 'Cadastrar com evidência'}
            </Button>
          </form>
          {criado && (
            <Alert role="status" data-testid="assistido-criado" className="mt-4">
              <AlertDescription>
                Cadastrado: {criado.nome} — matrícula <code>{criado.matricula}</code>
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>
    </section>
  )
}