import { useCallback, useEffect, useState } from 'react'
import { MonitorSmartphoneIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
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

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/sessions', { headers: cabecalhos() })
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
                <TableHead aria-label="Ações" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {sessoes.map((s) => (
                <TableRow key={s.id}>
                  <TableCell>{s.dispositivo ?? 'Dispositivo'}</TableCell>
                  <TableCell className="text-right">
                    <Button type="button" variant="outline" size="sm" onClick={() => s.id && revogar(s.id)}>
                      Revogar
                    </Button>
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