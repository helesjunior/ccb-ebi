import { useCallback, useEffect, useState } from 'react'
import { toast } from 'sonner'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import type { components } from '../api/schema'
import { cabecalhos, type ContextoAtuacao } from '../api/sessao'
import {
  criarEquipe,
  equipeDoEbi,
  incluirMembroEquipe,
  listarEquipes,
  obterEquipe,
  retirarMembroEquipe,
  type Equipe,
  type EquipeResumo,
} from '../api/escalas'
import { EstadoTabela } from '../components/EstadoTabela'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'

type Usuario = components['schemas']['UsuarioDetalhe']

/**
 * Equipes de escala: cada coordenadora tem a própria equipe de auxiliares e a
 * escala nasce de uma delas. A tela lista as equipes do EBI e abre o detalhe;
 * cada pessoa só edita os membros da equipe da qual é dona — o resto é o
 * servidor quem decide (aqui o botão apenas evita um pedido que já seria 403).
 */
export function EquipesEscala({
  contexto,
  papeis,
  usuario,
}: {
  contexto: ContextoAtuacao | null
  papeis: string[]
  usuario: Usuario | null
}) {
  const [ebis, setEbis] = useState<components['schemas']['EbiResumo'][]>([])
  const [ebiId, setEbiId] = useState('')
  const [equipes, setEquipes] = useState<EquipeResumo[]>([])
  const [equipe, setEquipe] = useState<Equipe | null>(null)
  const [candidatos, setCandidatos] = useState<components['schemas']['MembroEquipe'][]>([])
  const [carregando, setCarregando] = useState(true)
  const [enviando, setEnviando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  const eu = usuario?.id ?? ''
  const papeisEfetivos = usuario?.papeis ?? papeis
  const podeGerir = papeisEfetivos.some((p) => ['coordenadora', 'coordenadora_chefe', 'adm_igreja'].includes(p))
  const ebiDoContexto = contexto?.tipo_escopo === 'ebi' ? contexto.escopo_id : ''

  useEffect(() => {
    let vivo = true
    async function carregarEbis() {
      const { data } = await api.GET('/me/ebis', { headers: cabecalhos() })
      if (!vivo) return
      const lista = data?.data ?? []
      setEbis(lista)
      const inicial = ebiDoContexto || lista[0]?.id || ''
      if (inicial) setEbiId(inicial)
      if (lista.length === 0) setCarregando(false)
    }
    void carregarEbis()
    return () => { vivo = false }
  }, [ebiDoContexto])

  const carregarEquipes = useCallback(async () => {
    if (!ebiId) return
    setErro(null)
    const { data, error } = await listarEquipes(ebiId)
    if (error) setErro(lerErro(error, 'Não foi possível carregar as equipes.').detail)
    setEquipes(data ?? [])
    setCarregando(false)
    // Abre a primeira equipe (ou a que já estava aberta) sem pedir clique.
    const alvo = equipe?.id ?? data?.[0]?.id
    if (alvo) await abrirEquipe(alvo)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ebiId])

  // eslint-disable-next-line react-hooks/set-state-in-effect
  useEffect(() => { void carregarEquipes() }, [carregarEquipes]) // eslint-disable-line react-hooks/exhaustive-deps

  async function abrirEquipe(equipeId: string) {
    const { data, error } = await obterEquipe(equipeId)
    if (error) {
      setErro(lerErro(error, 'Não foi possível carregar a equipe.').detail)
      return
    }
    setEquipe(data ?? null)
    await carregarCandidatos()
  }

  /** Quem ainda pode entrar: a equipe do EBI menos quem já está nesta equipe. */
  async function carregarCandidatos() {
    if (!ebiId) return
    const { data } = await equipeDoEbi(ebiId)
    setCandidatos(data ?? [])
  }

  async function executar(acao: () => Promise<{ data?: Equipe; error?: unknown }>, sucesso: string) {
    setEnviando(true)
    setErro(null)
    const { data, error } = await acao()
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível salvar.').detail)
      return
    }
    setEquipe(data ?? equipe)
    setEquipes((lista) => lista.map((item) => (item.id === data?.id
      ? { ...item, quantidade_membros: data?.membros?.length ?? item.quantidade_membros }
      : item)))
    await carregarCandidatos()
    toast.success(sucesso)
  }

  async function criarMinha() {
    if (!ebiId) return
    setEnviando(true)
    setErro(null)
    const { data, error } = await criarEquipe(ebiId, eu)
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível criar a equipe.').detail)
      return
    }
    toast.success('Equipe criada.')
    await carregarEquipes()
    if (data?.id) await abrirEquipe(data.id)
  }

  /** Quem está aberto nesta tela; escreve só a dona dela (e só se gerencia). */
  const escreve = podeGerir && equipe !== null && equipe.coordenadora_id === eu
  const daEquipe = new Set((equipe?.membros ?? []).map((m) => m.usuario_id))
  const disponiveis = candidatos.filter((c) => c.usuario_id && !daEquipe.has(c.usuario_id))
  const temEquipeMinha = equipes.some((e) => e.coordenadora_id === eu)
  const podeCriar = podeGerir && !temEquipeMinha && eu !== ''

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold">Equipes de escala</h1>
        <p className="text-sm text-muted-foreground">
          Cada coordenadora monta a própria equipe de auxiliares; a escala de uma sessão nasce de uma delas.
          Uma auxiliar pode estar em mais de uma equipe.
        </p>
      </div>

      {erro && (
        <Alert variant="destructive">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}

      {ebis.length > 1 && (
        <div className="flex flex-wrap gap-2">
          {ebis.map((ebi) => (
            <Button
              key={ebi.id}
              type="button"
              size="sm"
              variant={ebi.id === ebiId ? 'default' : 'outline'}
              onClick={() => setEbiId(ebi.id ?? '')}
            >
              {ebi.nome}
            </Button>
          ))}
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Equipes do EBI</CardTitle>
          <CardDescription>Toque em uma equipe para ver e ajustar os membros.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          {carregando ? (
            <p className="text-sm text-muted-foreground">Carregando…</p>
          ) : equipes.length === 0 ? (
            <EstadoTabela vazio="Nenhuma equipe criada neste EBI." />
          ) : (
            <ul className="flex flex-col gap-2">
              {equipes.map((item) => (
                <li key={item.id}>
                  <Button
                    type="button"
                    variant={item.id === equipe?.id ? 'secondary' : 'ghost'}
                    className="h-auto w-full justify-start gap-2 px-3 py-2"
                    onClick={() => void abrirEquipe(item.id ?? '')}
                  >
                    <span className="font-medium">{item.coordenadora_nome ?? 'Coordenadora'}</span>
                    <span className="text-muted-foreground">
                      · {item.quantidade_membros ?? 0} {(item.quantidade_membros ?? 0) === 1 ? 'membro' : 'membros'}
                    </span>
                    {item.coordenadora_sem_vinculo && <Badge variant="destructive">coordenadora sem vínculo</Badge>}
                  </Button>
                </li>
              ))}
            </ul>
          )}

          {podeCriar && (
            <Button type="button" className="self-start" disabled={enviando} onClick={() => void criarMinha()}>
              Criar minha equipe
            </Button>
          )}
        </CardContent>
      </Card>

      {equipe && (
        <Card>
          <CardHeader>
            <CardTitle>
              Equipe de {equipe.coordenadora_nome ?? 'coordenadora'}
              {equipe.coordenadora_sem_vinculo && <Badge variant="destructive" className="ml-2">coordenadora sem vínculo</Badge>}
            </CardTitle>
            <CardDescription>
              {escreve
                ? 'Você é a dona desta equipe e pode incluir e retirar pessoas.'
                : 'Somente a dona da equipe inclui e retira pessoas.'}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            {(equipe.membros ?? []).length === 0 ? (
              <EstadoTabela vazio="Equipe ainda sem membros." />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Pessoa</TableHead>
                    <TableHead>Situação</TableHead>
                    {escreve && <TableHead className="text-right">Ações</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(equipe.membros ?? []).map((membro) => (
                    <TableRow key={membro.usuario_id}>
                      <TableCell>{membro.nome}</TableCell>
                      <TableCell>
                        {membro.sem_vinculo
                          ? <Badge variant="destructive">sem vínculo</Badge>
                          : <span className="text-sm text-muted-foreground">Vigente</span>}
                      </TableCell>
                      {escreve && (
                        <TableCell className="text-right">
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={enviando}
                            onClick={() => void executar(
                              () => retirarMembroEquipe(equipe.id ?? '', membro.usuario_id ?? ''),
                              'Membro retirado da equipe.',
                            )}
                          >
                            Retirar
                          </Button>
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}

            {escreve && (
              <div className="flex flex-col gap-2">
                <p className="text-sm font-medium">Incluir da equipe do EBI</p>
                {disponiveis.length === 0 ? (
                  <p className="text-sm text-muted-foreground">Ninguém disponível para incluir.</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {disponiveis.map((pessoa) => (
                      <Button
                        key={pessoa.usuario_id}
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={enviando}
                        onClick={() => void executar(
                          () => incluirMembroEquipe(equipe.id ?? '', pessoa.usuario_id ?? ''),
                          'Membro incluído na equipe.',
                        )}
                      >
                        {pessoa.nome}
                      </Button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
