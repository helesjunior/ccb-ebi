import { useCallback, useEffect, useState } from 'react'
import { UsersIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'
import { Alert, AlertDescription } from '@/components/ui/alert'
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

type Participante = components['schemas']['Participante']

/**
 * Dependentes: participantes que o usuário representa (autorização por
 * propriedade, CA-04). Só aparecem vínculos `aprovada` e vigentes — o servidor
 * decide, a tela apenas exibe. Nunca depende de território nem da matrícula.
 */
export function Dependentes() {
  const [dependentes, setDependentes] = useState<Participante[]>([])
  const [erro, setErro] = useState<string | null>(null)

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/participants', { headers: cabecalhos() })
    if (error || !data) {
      setErro(lerErro(error, 'Não foi possível carregar seus dependentes.').detail)
      return
    }
    setDependentes(data.data ?? [])
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UsersIcon className="size-5" aria-hidden="true" />
            Meus dependentes
          </CardTitle>
          <CardDescription>Participantes que você representa.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {erro && (
            <Alert variant="destructive">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          {dependentes.length === 0 && !erro ? (
            <p data-testid="sem-dependentes" className="text-sm text-muted-foreground">
              Nenhum dependente vinculado.
            </p>
          ) : (
            <Table data-testid="dependentes">
              <TableHeader>
                <TableRow>
                  <TableHead>Nome</TableHead>
                  <TableHead>Nascimento</TableHead>
                  <TableHead>Matrícula</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {dependentes.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>{p.nome}</TableCell>
                    <TableCell>{p.data_nascimento}</TableCell>
                    <TableCell>{p.matricula}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </section>
  )
}