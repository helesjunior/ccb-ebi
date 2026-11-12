import { useCallback, useEffect, useState } from 'react'
import { MonitorSmartphoneIcon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import { ConfirmarAcao } from '../components/ConfirmarAcao'
import { EstadoTabela } from '../components/EstadoTabela'
import { formatarDataHora } from '@/lib/formatos'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

interface SessaoLogin {
  id?: string
  dispositivo?: string
  criada_em?: string
  expira_em?: string
}

/**
 * Dispositivos: lista as sessões vigentes e permite revogar cada uma
 * individualmente (RF-05).
 */
export function Dispositivos() {
  const [sessoes, setSessoes] = useState<SessaoLogin[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(true)

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/sessions', { headers: cabecalhos() })
    setCarregando(false)
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível carregar os dispositivos.').detail)
      return
    }
    setSessoes(data.data ?? [])
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  async function revogar(id: string) {
    const { error } = await api.DELETE('/me/sessions/{id}', {
      params: { path: { id } },
      headers: cabecalhos(),
    })
    if (error) {
      setErro(lerErro(error, 'Não foi possível revogar o dispositivo.').detail)
      return
    }
    toast.success('Dispositivo desconectado.')
    await carregar()
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <MonitorSmartphoneIcon className="size-5" aria-hidden="true" />
            Dispositivos
          </CardTitle>
          <CardDescription>Sessões vigentes na sua conta. Revogue as que não reconhece.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {erro && (
            <Alert variant="destructive">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          <Table data-testid="sessoes">
            <TableHeader>
              <TableRow>
                <TableHead>Dispositivo</TableHead>
                <TableHead className="hidden sm:table-cell">Conectado em</TableHead>
                <TableHead className="hidden sm:table-cell">Expira em</TableHead>
                <TableHead className="text-right">
                  <span className="sr-only">Ações</span>
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={4}
                carregando={carregando}
                vazio={sessoes.length === 0 && !erro}
                mensagemVazio="Nenhuma sessão ativa além desta."
              />
              {sessoes.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="font-medium whitespace-normal">
                    {s.dispositivo ?? 'Dispositivo desconhecido'}
                    <span className="block text-xs font-normal text-muted-foreground sm:hidden">
                      Desde {formatarDataHora(s.criada_em)}
                    </span>
                  </TableCell>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">
                    {formatarDataHora(s.criada_em)}
                  </TableCell>
                  <TableCell className="hidden text-muted-foreground sm:table-cell">
                    {formatarDataHora(s.expira_em)}
                  </TableCell>
                  <TableCell className="text-right">
                    <ConfirmarAcao
                      titulo="Desconectar este dispositivo?"
                      descricao="Quem estiver usando a conta nele precisará entrar de novo com e-mail e senha."
                      rotuloConfirmar="Desconectar"
                      onConfirmar={() => (s.id ? revogar(s.id) : undefined)}
                    >
                      <Button type="button" variant="outline" size="sm">
                        Revogar
                      </Button>
                    </ConfirmarAcao>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </section>
  )
}