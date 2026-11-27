import { useState, type FormEvent, type ReactNode } from 'react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import type { Participante } from './FormularioParticipante'
import { hojeIso } from '@/lib/formatos'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

type Correcao = components['schemas']['CorrecaoParticipanteInput']

function inicial(p: Participante) {
  return {
    nome: p.nome,
    nome_social: p.nome_social ?? '',
    data_nascimento: p.data_nascimento,
    igreja_referencia_id: p.igreja_referencia_id ?? '',
  }
}

/**
 * Correção de cadastro (CA-27, CA1): manda só o que mudou, com motivo
 * obrigatório. Erros da API ficam no diálogo, que continua aberto.
 */
export function CorrigirParticipante({
  participante,
  onCorrigido,
  children,
}: {
  participante: Participante
  onCorrigido: (p: Participante) => void
  children: ReactNode
}) {
  const [aberto, setAberto] = useState(false)
  const [dados, setDados] = useState(() => inicial(participante))
  const [motivo, setMotivo] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  const original = inicial(participante)
  const mudou = (Object.keys(dados) as (keyof typeof dados)[]).filter((k) => dados[k] !== original[k])
  const motivoValido = motivo.trim().length >= 3 && motivo.trim().length <= 255

  function abrir(valor: boolean) {
    if (enviando) return
    if (valor) {
      setDados(inicial(participante))
      setMotivo('')
      setErro(null)
    }
    setAberto(valor)
  }

  function campo(chave: keyof typeof dados) {
    return (e: { target: { value: string } }) => setDados((atual) => ({ ...atual, [chave]: e.target.value }))
  }

  async function enviar(evento: FormEvent) {
    evento.preventDefault()
    if (enviando || mudou.length === 0 || !motivoValido || !participante.id) return
    const body: Correcao = { motivo: motivo.trim() }
    for (const k of mudou) {
      const valor = dados[k].trim()
      if (k === 'nome_social' || k === 'igreja_referencia_id') body[k] = valor || null
      else body[k] = valor
    }
    setEnviando(true)
    setErro(null)
    const { data, error } = await api.PATCH('/participants/{id}', {
      params: { path: { id: participante.id } }, headers: cabecalhos(), body,
    })
    setEnviando(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível corrigir o cadastro.').detail)
      return
    }
    onCorrigido(data)
    toast.success('Cadastro corrigido.')
    setAberto(false)
  }

  return (
    <Dialog open={aberto} onOpenChange={abrir}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent showCloseButton={!enviando}>
        <DialogHeader>
          <DialogTitle>Corrigir cadastro</DialogTitle>
          <DialogDescription>Altere só o que estiver errado e informe o motivo.</DialogDescription>
        </DialogHeader>
        <form onSubmit={enviar} noValidate className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="correcao-nome">Nome completo</Label>
            <Input id="correcao-nome" autoComplete="off" value={dados.nome} onChange={campo('nome')} maxLength={255} required disabled={enviando} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="correcao-nome-social">Nome social (opcional)</Label>
            <Input id="correcao-nome-social" value={dados.nome_social} onChange={campo('nome_social')} maxLength={255} disabled={enviando} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="correcao-nascimento">Data de nascimento</Label>
            <Input id="correcao-nascimento" type="date" max={hojeIso()} value={dados.data_nascimento} onChange={campo('data_nascimento')} required disabled={enviando} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="correcao-igreja">Igreja de referência (opcional)</Label>
            <Input id="correcao-igreja" value={dados.igreja_referencia_id} onChange={campo('igreja_referencia_id')} disabled={enviando} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="correcao-motivo">Motivo da correção (3 a 255 caracteres)</Label>
            <Input id="correcao-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} minLength={3} maxLength={255} required disabled={enviando} />
          </div>
          {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}
          <DialogFooter>
            <Button type="button" variant="outline" disabled={enviando} onClick={() => setAberto(false)}>Voltar</Button>
            <Button type="submit" disabled={enviando || mudou.length === 0 || !motivoValido}>
              {enviando ? 'Salvando…' : 'Salvar correção'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
