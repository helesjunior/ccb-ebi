import { useState } from 'react'
import { SearchIcon, UserPlusIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import {
  FormularioParticipante,
  type Participante,
} from '../components/FormularioParticipante'
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

/**
 * Participantes: cadastra um participante e busca por nome (dedup, CA-27).
 *
 * A leitura identificável exige uma **finalidade declarada** (matriz `*` da
 * leitura): ela vai no parâmetro `finalidade` e é registrada na auditoria do
 * servidor. Sem ela, a API recusa a busca (422).
 */
export function Participantes() {
  const [criado, setCriado] = useState<Participante | null>(null)
  const [termo, setTermo] = useState('')
  const [finalidade, setFinalidade] = useState('confirmacao_cadastro')
  const [resultados, setResultados] = useState<Participante[]>([])
  const [erroBusca, setErroBusca] = useState<string | null>(null)
  const [buscando, setBuscando] = useState(false)

  async function buscar() {
    setErroBusca(null)
    setBuscando(true)
    const { data, error } = await api.GET('/participants', {
      params: { query: { q: termo, finalidade } },
      headers: cabecalhos(),
    })
    setBuscando(false)
    if (error || !data) {
      setErroBusca(lerErro(error, 'Não foi possível buscar participantes.').detail)
      return
    }
    setResultados(data.data ?? [])
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UserPlusIcon className="size-5" aria-hidden="true" />
            Cadastrar
          </CardTitle>
          <CardDescription>Cadastre um participante sob sua responsabilidade.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <FormularioParticipante onCriado={setCriado} />
          {criado && (
            <Alert role="status" data-testid="participante-criado">
              <AlertDescription>
                Cadastrado: {criado.nome} — matrícula <code>{criado.matricula}</code>
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <SearchIcon className="size-5" aria-hidden="true" />
            Buscar (dedup)
          </CardTitle>
          <CardDescription>Confira se já existe cadastro antes de criar um novo.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <Label htmlFor="busca-termo">Nome</Label>
            <Input id="busca-termo" value={termo} onChange={(e) => setTermo(e.target.value)} />
          </div>
          <div className="flex flex-col gap-2">
            <Label htmlFor="busca-finalidade">Finalidade da leitura</Label>
            <Input
              id="busca-finalidade"
              value={finalidade}
              onChange={(e) => setFinalidade(e.target.value)}
              required
            />
          </div>
          <Button
            type="button"
            onClick={buscar}
            disabled={buscando || !termo || !finalidade}
            className="self-start"
          >
            {buscando ? 'Buscando…' : 'Buscar'}
          </Button>
          {erroBusca && (
            <Alert variant="destructive">
              <AlertDescription>{erroBusca}</AlertDescription>
            </Alert>
          )}
          <Table data-testid="resultados">
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Nascimento</TableHead>
                <TableHead>Matrícula</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {resultados.map((p) => (
                <TableRow key={p.id}>
                  <TableCell>{p.nome}</TableCell>
                  <TableCell>{p.data_nascimento}</TableCell>
                  <TableCell>{p.matricula}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </section>
  )
}