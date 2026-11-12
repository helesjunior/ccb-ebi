import { useRef, useState, type FormEvent } from 'react'
import { FileUpIcon, UserPlusIcon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { Obrigatorio } from '../components/Obrigatorio'
import { Sugestoes } from '../components/Sugestoes'
import { hojeIso, SUGESTOES_RELACAO } from '@/lib/formatos'
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
  const formularioRef = useRef<HTMLFormElement>(null)
  const completo = nome.trim() && dataNascimento && responsavelNome.trim() && tipoRelacao.trim() && evidencia

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
    toast.success(`${data.nome} cadastrado(a) com evidência.`)
    formularioRef.current?.reset()
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
            Cadastro presencial pela coordenação. Anexe a foto ou o PDF do termo assinado pelo
            responsável. Campos com <Obrigatorio /> são obrigatórios.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form
            ref={formularioRef}
            onSubmit={enviar}
            noValidate
            className="grid gap-4 sm:grid-cols-2"
          >
            <div className="flex flex-col gap-2 sm:col-span-2">
              <Label htmlFor="assistido-nome">
                Nome completo da criança <Obrigatorio />
              </Label>
              <Input
                autoComplete="off"
                id="assistido-nome"
                value={nome}
                onChange={(e) => setNome(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-nascimento">
                Data de nascimento <Obrigatorio />
              </Label>
              <Input
                id="assistido-nascimento"
                max={hojeIso()}
                type="date"
                value={dataNascimento}
                onChange={(e) => setDataNascimento(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-responsavel">
                Nome do responsável <Obrigatorio />
              </Label>
              <Input
                autoComplete="off"
                id="assistido-responsavel"
                value={responsavelNome}
                onChange={(e) => setResponsavelNome(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="assistido-relacao">
                Relação com a criança <Obrigatorio />
              </Label>
              <Sugestoes id="assistido-relacao-sugestoes" opcoes={SUGESTOES_RELACAO} />
              <Input
                list="assistido-relacao-sugestoes"
                id="assistido-relacao"
                value={tipoRelacao}
                onChange={(e) => setTipoRelacao(e.target.value)}
                required
              />
            </div>
            <div className="flex flex-col gap-2 sm:col-span-2">
              <Label htmlFor="assistido-evidencia" className="flex items-center gap-2">
                <FileUpIcon className="size-4" aria-hidden="true" />
                Evidência de aceite <Obrigatorio />
              </Label>
              <Input
                id="assistido-evidencia"
                type="file"
                accept="application/pdf,image/jpeg,image/png"
                onChange={(e) => setEvidencia(e.target.files?.[0] ?? null)}
                aria-describedby="assistido-evidencia-ajuda"
                className="cursor-pointer file:mr-3 file:cursor-pointer file:rounded file:bg-secondary file:px-2"
                required
              />
              <p id="assistido-evidencia-ajuda" className="text-xs text-muted-foreground">
                {evidencia
                  ? `Selecionado: ${evidencia.name} (${Math.ceil(evidencia.size / 1024)} KB)`
                  : 'PDF, JPEG ou PNG. O arquivo é verificado e guardado cifrado.'}
              </p>
            </div>
            {erro && (
              <Alert variant="destructive" role="alert" className="sm:col-span-2">
                <AlertDescription>{erro}</AlertDescription>
              </Alert>
            )}
            <Button
              type="submit"
              disabled={enviando || !completo}
              className="sm:col-span-2 sm:justify-self-start"
            >
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