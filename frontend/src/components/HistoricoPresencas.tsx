import { useCallback, useEffect, useState } from 'react'
import { HistoryIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import { EstadoTabela } from './EstadoTabela'
import { formatarDataHora, rotuloStatus } from '@/lib/formatos'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

type Historico = components['schemas']['HistoricoPresenca']

/**
 * Histórico de presenças de um participante, em diálogo: o operador usa a rota
 * de escopo (`/participants/{id}/attendance`) e o responsável, a de propriedade
 * (`/me/participants/{id}/attendance`). A API decide o acesso; a tela só exibe.
 */
export function HistoricoPresencas({ participanteId, rota }: {
  participanteId: string
  rota: 'operador' | 'responsavel'
}) {
  const [aberto, setAberto] = useState(false)
  const [itens, setItens] = useState<Historico[]>([])
  const [carregando, setCarregando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  const carregar = useCallback(async () => {
    setCarregando(true)
    setErro(null)
    const resposta = rota === 'operador'
      ? await api.GET('/participants/{id}/attendance', {
        params: { path: { id: participanteId } }, headers: cabecalhos(),
      })
      : await api.GET('/me/participants/{id}/attendance', {
        params: { path: { id: participanteId } }, headers: cabecalhos(),
      })
    setCarregando(false)
    if (resposta.error || !resposta.data) {
      setErro(lerErro(resposta.error, 'Não foi possível carregar o histórico de presenças.').detail)
      return
    }
    setItens(resposta.data.data ?? [])
  }, [participanteId, rota])

  useEffect(() => {
    if (aberto) void carregar()
  }, [aberto, carregar])

  return (
    <>
      <Button type="button" variant="ghost" size="sm" onClick={() => setAberto(true)}>
        <HistoryIcon className="size-4" aria-hidden="true" />
        Histórico
      </Button>
      <Dialog open={aberto} onOpenChange={setAberto}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Histórico de presenças</DialogTitle>
            <DialogDescription>Presenças registradas em sessões do EBI.</DialogDescription>
          </DialogHeader>
          {erro && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Data</TableHead>
                <TableHead>EBI</TableHead>
                <TableHead>Situação</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={3}
                carregando={carregando}
                vazio={itens.length === 0}
                mensagemVazio="Nenhuma presença registrada."
              />
              {!carregando && itens.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>{formatarDataHora(item.inicio_previsto)}</TableCell>
                  <TableCell className="whitespace-normal">{item.ebi?.nome ?? '—'}</TableCell>
                  <TableCell>{rotuloStatus(item.status)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </DialogContent>
      </Dialog>
    </>
  )
}
