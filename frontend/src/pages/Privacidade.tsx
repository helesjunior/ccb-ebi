import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { ShieldCheckIcon } from 'lucide-react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { ConfirmarAcao } from '../components/ConfirmarAcao'
import { formatarData, rotuloStatus } from '@/lib/formatos'
import { Badge } from '@/components/ui/badge'
import { toast } from 'sonner'
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
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

type TermoVersao = components['schemas']['TermoVersao']
type Manifestacao = components['schemas']['ManifestacaoPrivacidade']
type TipoSolicitacao = components['schemas']['SolicitacaoInput']['tipo']

const TIPOS_SOLICITACAO: { valor: TipoSolicitacao; rotulo: string }[] = [
  { valor: 'acesso', rotulo: 'Acesso aos meus dados' },
  { valor: 'correcao', rotulo: 'Correção de dados' },
  { valor: 'revogacao', rotulo: 'Revogação de consentimento' },
]

/**
 * Privacidade: mostra o termo de cadastro vigente, as manifestações de
 * consentimento atuais do próprio titular, permite aceitar/recusar e retirar o
 * consentimento, além de abrir uma solicitação de direito (RF-16).
 */
export function Privacidade() {
  const [termo, setTermo] = useState<TermoVersao | null>(null)
  const [manifestacoes, setManifestacoes] = useState<Manifestacao[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  const [tipo, setTipo] = useState<TipoSolicitacao>('acesso')
  const [descricao, setDescricao] = useState('')
  const [erroSolicitacao, setErroSolicitacao] = useState<string | null>(null)
  const [protocolo, setProtocolo] = useState<string | null>(null)

  const carregar = useCallback(async () => {
    const [t, m] = await Promise.all([
      api.GET('/privacy/terms', {
        params: { query: { finalidade: 'cadastro' } },
        headers: cabecalhos(),
      }),
      api.GET('/privacy/consents', { headers: cabecalhos() }),
    ])
    if (t.error || !t.data) {
      setErro(lerErro(t.error, 'Não foi possível carregar o termo de privacidade.').detail)
      return
    }
    setTermo(t.data.data?.[0] ?? null)
    if (!m.error && m.data) {
      setManifestacoes(m.data.data ?? [])
    }
  }, [])

  useEffect(() => {
    void carregar()
  }, [carregar])

  /** Manifestação mais recente para a finalidade `cadastro`. */
  const manifestacaoCadastro = manifestacoes.find((x) => x.termo_versao?.finalidade === 'cadastro')
  const aceito = manifestacaoCadastro?.decisao === 'aceito'

  /**
   * Texto do termo para leitura ANTES de manifestar. A fonte primaria e o
   * `GET /privacy/terms` (payload informado); se por algum motivo ele vier
   * ausente, cai para o texto aninhado na manifestacao ja existente.
   */
  const textoTermo = termo?.texto ?? manifestacaoCadastro?.termo_versao?.texto ?? null

  /**
   * Registra uma manifestação. `retirar: true` aciona o caminho de retirada no
   * servidor (`RegistrarConsentimento::retirar` → decisão `retirado`), que tem
   * evidência própria — por isso a retirada envia `decisao: 'aceito'` e deixa o
   * `retirar` mandar na semântica (o `decisao` só é lido quando `retirar` é falso).
   */
  async function registrar(decisao: 'aceito' | 'recusado', retirar = false) {
    if (!termo?.id) {
      setErro('Termo de cadastro indisponível.')
      return
    }
    setErro(null)
    setEnviando(true)
    const { error } = await api.POST('/privacy/consents', {
      headers: cabecalhos(),
      body: { termo_versao_id: termo.id, decisao, retirar },
    })
    setEnviando(false)
    if (error) {
      setErro(lerErro(error, 'Não foi possível registrar sua manifestação.').detail)
      return
    }
    toast.success(
      retirar
        ? 'Consentimento retirado.'
        : decisao === 'aceito'
          ? 'Termo aceito. Obrigado!'
          : 'Recusa registrada.',
    )
    await carregar()
  }

  async function abrirSolicitacao(evento: FormEvent) {
    evento.preventDefault()
    setErroSolicitacao(null)
    setProtocolo(null)
    setEnviando(true)
    const { data, error } = await api.POST('/privacy/requests', {
      headers: cabecalhos(),
      body: { tipo, descricao: descricao.trim() || undefined },
    })
    setEnviando(false)
    if (error || !data) {
      setErroSolicitacao(lerErro(error, 'Não foi possível abrir a solicitação.').detail)
      return
    }
    setProtocolo(data.protocolo)
    toast.success('Solicitação aberta. Guarde o protocolo.')
    setDescricao('')
  }

  return (
    <section className="mx-auto flex max-w-3xl flex-col gap-6">
      {erro && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShieldCheckIcon className="size-5" aria-hidden="true" />
            Termo de cadastro
          </CardTitle>
          {termo && (
            <CardDescription>
              Versão {termo.versao ?? '—'} · base legal {termo.base_legal ?? '—'}
              {termo.vigente_em
                ? ` · vigente desde ${formatarData(termo.vigente_em)}`
                : ''}
            </CardDescription>
          )}
        </CardHeader>
        <CardContent className="flex flex-col gap-4 text-sm">
          {termo ? (
            <div data-testid="termo-vigente" className="flex flex-col gap-2">
              {textoTermo ? (
                <section aria-labelledby="termo-texto-titulo">
                  <h3 id="termo-texto-titulo" className="mb-1 font-medium">
                    Texto do termo
                  </h3>
                  <p
                    data-testid="termo-texto"
                    className="max-h-72 overflow-y-auto rounded-md border bg-muted/40 p-3 leading-relaxed whitespace-pre-line text-muted-foreground"
                    tabIndex={0}
                  >
                    {textoTermo}
                  </p>
                </section>
              ) : (
                <p data-testid="termo-texto-indisponivel" className="text-muted-foreground">
                  O texto integral do termo não está disponível no momento.
                </p>
              )}
            </div>
          ) : (
            <p className="text-muted-foreground">Nenhum termo vigente para o cadastro.</p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Seu consentimento</CardTitle>
          <CardDescription data-testid="consentimento-atual" className="flex flex-wrap items-center gap-2">
            {manifestacaoCadastro ? (
              <>
                Situação atual:
                <Badge variant={aceito ? 'default' : 'outline'}>
                  {aceito ? 'Aceito' : rotuloStatus(manifestacaoCadastro.decisao)}
                </Badge>
              </>
            ) : (
              'Você ainda não registrou uma manifestação.'
            )}
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          {aceito ? (
            <ConfirmarAcao
              titulo="Retirar o consentimento?"
              descricao="A retirada fica registrada. Alguns serviços que dependem deste termo podem deixar de funcionar para você."
              rotuloConfirmar="Retirar consentimento"
              onConfirmar={() => registrar('aceito', true)}
            >
              <Button type="button" variant="destructive" disabled={enviando || !termo?.id}>
                Retirar consentimento
              </Button>
            </ConfirmarAcao>
          ) : (
            <Button
              type="button"
              onClick={() => registrar('aceito', false)}
              disabled={enviando || !termo?.id}
            >
              Aceitar termo
            </Button>
          )}
          {!aceito && (
            <Button
              type="button"
              variant="outline"
              onClick={() => registrar('recusado', false)}
              disabled={enviando || !termo?.id}
            >
              Recusar termo
            </Button>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Solicitação</CardTitle>
          <CardDescription>Abra uma solicitação de direito sobre os seus dados.</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={abrirSolicitacao} noValidate className="flex flex-col gap-4">
            <div className="flex flex-col gap-2">
              <Label htmlFor="solicitacao-tipo">Tipo de solicitação</Label>
              <Select value={tipo} onValueChange={(v) => setTipo(v as TipoSolicitacao)}>
                <SelectTrigger id="solicitacao-tipo" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TIPOS_SOLICITACAO.map((t) => (
                    <SelectItem key={t.valor} value={t.valor}>
                      {t.rotulo}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex flex-col gap-2">
              <Label htmlFor="solicitacao-descricao">Descrição (opcional)</Label>
              <textarea
                id="solicitacao-descricao"
                value={descricao}
                onChange={(e) => setDescricao(e.target.value)}
                className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
              />
            </div>
            {erroSolicitacao && (
              <Alert variant="destructive">
                <AlertDescription>{erroSolicitacao}</AlertDescription>
              </Alert>
            )}
            <Button type="submit" disabled={enviando} className="self-start">
              {enviando ? 'Enviando…' : 'Abrir solicitação'}
            </Button>
          </form>
          {protocolo && (
            <Alert className="mt-4" role="status" data-testid="protocolo">
              <AlertDescription>
                Solicitação aberta. Protocolo: <code>{protocolo}</code>
              </AlertDescription>
            </Alert>
          )}
        </CardContent>
      </Card>
    </section>
  )
}