import { useCallback, useEffect, useState, type FormEvent } from 'react'

import { api } from '../api/client'
import { lerErro } from '../api/erro'
import { cabecalhos } from '../api/sessao'
import type { components } from '../api/schema'

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
    setDescricao('')
  }

  return (
    <main>
      <h1>Privacidade</h1>
      {erro && <p role="alert">{erro}</p>}

      <h2>Termo de cadastro</h2>
      {termo ? (
        <div data-testid="termo-vigente">
          <p>
            Versão {termo.versao ?? '—'} · base legal {termo.base_legal ?? '—'}
          </p>
          {termo.vigente_em && (
            <p>
              <small>Vigente desde {new Date(termo.vigente_em).toLocaleDateString('pt-BR')}</small>
            </p>
          )}
          {textoTermo ? (
            <section aria-labelledby="termo-texto-titulo">
              <h3 id="termo-texto-titulo">Texto do termo</h3>
              <p data-testid="termo-texto">{textoTermo}</p>
            </section>
          ) : (
            <p data-testid="termo-texto-indisponivel">
              <small>O texto integral do termo não está disponível no momento.</small>
            </p>
          )}
        </div>
      ) : (
        <p>Nenhum termo vigente para o cadastro.</p>
      )}

      <h2>Seu consentimento</h2>
      <p data-testid="consentimento-atual">
        {manifestacaoCadastro
          ? `Situação atual: ${aceito ? 'aceito' : manifestacaoCadastro.decisao}`
          : 'Você ainda não registrou uma manifestação.'}
      </p>
      <button
        type="button"
        onClick={() => registrar('aceito', aceito)}
        disabled={enviando || !termo?.id}
      >
        {aceito ? 'Retirar consentimento' : 'Aceitar termo'}
      </button>
      {!aceito && (
        <button
          type="button"
          onClick={() => registrar('recusado', false)}
          disabled={enviando || !termo?.id}
        >
          Recusar termo
        </button>
      )}

      <h2>Solicitação</h2>
      <form onSubmit={abrirSolicitacao} noValidate>
        <p>
          <label htmlFor="solicitacao-tipo">Tipo de solicitação</label>
          <select
            id="solicitacao-tipo"
            value={tipo}
            onChange={(e) => setTipo(e.target.value as TipoSolicitacao)}
          >
            {TIPOS_SOLICITACAO.map((t) => (
              <option key={t.valor} value={t.valor}>
                {t.rotulo}
              </option>
            ))}
          </select>
        </p>
        <p>
          <label htmlFor="solicitacao-descricao">Descrição (opcional)</label>
          <textarea
            id="solicitacao-descricao"
            value={descricao}
            onChange={(e) => setDescricao(e.target.value)}
          />
        </p>
        {erroSolicitacao && <p role="alert">{erroSolicitacao}</p>}
        <button type="submit" disabled={enviando}>
          {enviando ? 'Enviando…' : 'Abrir solicitação'}
        </button>
      </form>
      {protocolo && (
        <p role="status" data-testid="protocolo">
          Solicitação aberta. Protocolo: <code>{protocolo}</code>
        </p>
      )}
    </main>
  )
}