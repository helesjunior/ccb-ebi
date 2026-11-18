import { useCallback, useEffect, useState } from 'react'
import { UsersIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { EstadoTabela } from '../components/EstadoTabela'
import { HistoricoPresencas } from '../components/HistoricoPresencas'
import { formatarData } from '@/lib/formatos'
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
  const [carregando, setCarregando] = useState(true)

  const carregar = useCallback(async () => {
    const { data, error } = await api.GET('/me/participants', { headers: cabecalhos() })
    setCarregando(false)
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
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erro}</AlertDescription>
            </Alert>
          )}
          {dependentes.length === 0 && !erro && !carregando ? (
            <div
              data-testid="sem-dependentes"
              className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-8 text-center"
            >
              <UsersIcon className="size-8 text-muted-foreground opacity-60" aria-hidden="true" />
              <p className="font-medium">Nenhum dependente vinculado.</p>
              <p className="max-w-sm text-sm text-muted-foreground">
                Quando a coordenação aprovar um pedido de vínculo, a criança aparecerá aqui.
              </p>
            </div>
          ) : (
            <Table data-testid="dependentes">
              <TableHeader>
                <TableRow>
                  <TableHead>Nome</TableHead>
                  <TableHead>Nascimento</TableHead>
                  <TableHead>Matrícula</TableHead>
                  <TableHead>Ações</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <EstadoTabela colunas={4} carregando={carregando} vazio={false} mensagemVazio="" />
                {dependentes.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="font-medium whitespace-normal">{p.nome}</TableCell>
                    <TableCell>{formatarData(p.data_nascimento)}</TableCell>
                    <TableCell>
                      <code>{p.matricula}</code>
                    </TableCell>
                    <TableCell>
                      <HistoricoPresencas participanteId={p.id} rota="responsavel" />
                    </TableCell>
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