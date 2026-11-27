import { useState, type FormEvent } from 'react'
import { SearchIcon, UserPlusIcon } from 'lucide-react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos, type ContextoAtuacao } from '../api/sessao'
import { CorrigirParticipante } from '../components/CorrigirParticipante'
import {
  FormularioParticipante,
  type Participante,
} from '../components/FormularioParticipante'
import { EstadoTabela } from '../components/EstadoTabela'
import { HistoricoPresencas } from '../components/HistoricoPresencas'
import { Sugestoes } from '../components/Sugestoes'
import { formatarData, SUGESTOES_FINALIDADE } from '@/lib/formatos'
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
export function Participantes({ escopo }: { escopo: ContextoAtuacao | null }) {
  const [criado, setCriado] = useState<Participante | null>(null)
  const [termo, setTermo] = useState('')
  const [finalidade, setFinalidade] = useState('confirmacao_cadastro')
  const [resultados, setResultados] = useState<Participante[]>([])
  const [suprimido, setSuprimido] = useState(false)
  const [erroBusca, setErroBusca] = useState<string | null>(null)
  const [buscando, setBuscando] = useState(false)
  const [buscou, setBuscou] = useState(false)
  // O servidor confere o papel do contexto (ParticipantController::update), nao usuario.papeis.
  const podeCorrigir = ['coordenadora', 'coordenadora_chefe', 'super_admin'].includes(escopo?.papel ?? '')

  function aoCriar(p: Participante) {
    setCriado(p)
    toast.success(`${p.nome} cadastrado(a).`)
  }

  async function buscar(evento?: FormEvent) {
    evento?.preventDefault()
    if (!termo.trim() || !finalidade.trim()) return
    setErroBusca(null)
    setBuscando(true)
    const { data, error } = await api.GET('/participants', {
      params: { query: { q: termo, finalidade } },
      headers: cabecalhos(),
    })
    setBuscando(false)
    setSuprimido(false)
    if (error || !data) {
      setErroBusca(lerErro(error, 'Não foi possível buscar participantes.').detail)
      return
    }
    // Acima da igreja, um grupo de 1 a 4 vem escondido (ADR-0020): dizer
    // "ninguém encontrado" aqui seria mentira e revelaria o grupo.
    setSuprimido(data.suprimido === true)
    setResultados(data.data ?? [])
    setBuscou(true)
  }

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <SearchIcon className="size-5" aria-hidden="true" />
            Buscar participante
          </CardTitle>
          <CardDescription>
            Antes de cadastrar, confira se a pessoa já existe para evitar duplicidade.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <form
            onSubmit={buscar}
            role="search"
            noValidate
            className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
          >
            <div className="flex flex-col gap-2">
              <Label htmlFor="busca-termo">Nome</Label>
              <Input
                id="busca-termo"
                type="search"
                placeholder="Digite parte do nome"
                autoComplete="off"
                value={termo}
                onChange={(e) => setTermo(e.target.value)}
              />
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="busca-finalidade">Finalidade da leitura</Label>
              <Input
                id="busca-finalidade"
                list="busca-finalidade-sugestoes"
                value={finalidade}
                onChange={(e) => setFinalidade(e.target.value)}
                aria-describedby="busca-finalidade-ajuda"
                required
              />
              <Sugestoes id="busca-finalidade-sugestoes" opcoes={SUGESTOES_FINALIDADE} />
            </div>
            <Button type="submit" disabled={buscando || !termo.trim() || !finalidade.trim()}>
              <SearchIcon className="size-4" aria-hidden="true" />
              {buscando ? 'Buscando…' : 'Buscar'}
            </Button>
            <p id="busca-finalidade-ajuda" className="text-xs text-muted-foreground sm:col-span-3">
              A finalidade fica registrada na auditoria, porque a busca mostra dados pessoais.
            </p>
          </form>
          {erroBusca && (
            <Alert variant="destructive" role="alert">
              <AlertDescription>{erroBusca}</AlertDescription>
            </Alert>
          )}
          {buscou && !erroBusca && !buscando && !suprimido && (
            <p role="status" className="text-sm text-muted-foreground">
              {resultados.length === 1
                ? '1 participante encontrado.'
                : `${resultados.length} participantes encontrados.`}
            </p>
          )}
          {suprimido && !erroBusca && !buscando && (
            <Alert role="status" data-testid="busca-suprimida">
              <AlertDescription>
                Para proteger as pessoas, o sistema não divulga listas de menos de 5 resultados.
                Ajuste a busca ou consulte a igreja responsável.
              </AlertDescription>
            </Alert>
          )}
          <Table data-testid="resultados">
            <TableHeader>
              <TableRow>
                <TableHead>Nome</TableHead>
                <TableHead>Nascimento</TableHead>
                <TableHead>Matrícula</TableHead>
                <TableHead>Ações</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <EstadoTabela
                colunas={4}
                carregando={buscando}
                vazio={suprimido || resultados.length === 0}
                mensagemVazio={
                  suprimido
                    ? 'A lista tem menos de 5 pessoas e não é divulgada para o seu nível.'
                    : buscou
                      ? 'Ninguém encontrado com esse nome. Você pode cadastrar logo abaixo.'
                      : 'Faça uma busca para ver os resultados.'
                }
              />
              {!buscando &&
                resultados.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="font-medium whitespace-normal">{p.nome}</TableCell>
                    <TableCell>{formatarData(p.data_nascimento)}</TableCell>
                    <TableCell>
                      <code>{p.matricula}</code>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-2">
                      <HistoricoPresencas participanteId={p.id} rota="operador" />
                      {podeCorrigir && (
                        <CorrigirParticipante
                          participante={p}
                          onCorrigido={(novo) => setResultados((atuais) => atuais.map((r) => (r.id === novo.id ? novo : r)))}
                        >
                          <Button type="button" variant="outline" size="sm">Corrigir</Button>
                        </CorrigirParticipante>
                      )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <UserPlusIcon className="size-5" aria-hidden="true" />
            Cadastrar
          </CardTitle>
          <CardDescription>Cadastre um participante sob sua responsabilidade.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          <FormularioParticipante onCriado={aoCriar} />
          {criado && (
            <Alert role="status" data-testid="participante-criado">
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
