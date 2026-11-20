import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
  },
}))

import { Relatorios } from './Relatorios'
import type { ContextoAtuacao } from '../api/sessao'
import type { components } from '../api/schema'

type Motivo = components['schemas']['RelatorioIndicador']['motivo']

const escopo: ContextoAtuacao = { papel: 'adm_regional', tipo_escopo: 'regional', escopo_id: 'reg-1' }

function indicador(valor: number | null, suprimido = false, motivo: Motivo = null) {
  return { valor, suprimido, motivo }
}

const TOTAIS = {
  participantes_distintos: indicador(12),
  presencas: indicador(40),
  sessoes_realizadas: indicador(4),
  media_por_sessao: indicador(10),
  participantes_cadastrados: indicador(30),
  participantes_ativos: indicador(25),
  igrejas_com_ebi_ativo: indicador(3),
}

function relatorio(no: Partial<{ nivel: string; id: string; nome: string }> = {}) {
  return {
    no: { nivel: 'regional', id: 'reg-1', nome: 'Regional Sul', ...no },
    periodo: { inicio: '2026-10-21', fim: '2026-11-19' },
    fuso: 'America/Sao_Paulo',
    gerado_em: '2026-11-19T12:00:00+00:00',
    criterios: 'Participantes sem igreja de referencia nao entram.',
    totais: TOTAIS,
    filhos: [],
  }
}

function filho(id: string, nome: string) {
  return { nivel: 'igreja', id, nome, totais: TOTAIS }
}

/** Resposta no formato do openapi-fetch: corpo já lido + `Response` crua. */
function resposta(data: unknown) {
  const corpo = typeof data === 'string' ? data : JSON.stringify(data)
  return {
    data,
    response: new Response(corpo, {
      status: 200,
      headers: { 'Content-Type': typeof data === 'string' ? 'text/csv' : 'application/json' },
    }),
  }
}

/** Consulta do GET, com o nó e o formato que o componente pediu. */
function consultaDe(chamada: unknown[]) {
  const opcoes = chamada[1] as { params?: { query?: Record<string, string> } }
  return opcoes?.params?.query ?? {}
}

/** O título é quebrado em nós (`nome` + `(nível)`): casa pelo texto do nó. */
async function acharTitulo(esperado: string) {
  return waitFor(() => {
    const achados = screen
      .getAllByText((_, elemento) => elemento?.textContent === esperado)
      .filter((el) => el.textContent === esperado)
    const folha = achados[achados.length - 1]
    if (!folha) throw new Error(`Título não encontrado: ${esperado}`)
    return folha
  })
}

beforeEach(() => {
  getJson.mockReset()
})

describe('tela de relatorios', () => {
  it('exporta o CSV como texto do no aberto', async () => {
    const usuario = userEvent.setup()
    getJson.mockImplementation(async (_caminho: string, opcoes: { params?: { query?: Record<string, string> } }) =>
      opcoes.params?.query?.formato === 'csv'
        ? resposta('Unidade;Presencas\nRegional Sul;40\n')
        : resposta({ ...relatorio(), filhos: [filho('ig-1', 'Igreja Centro')] }),
    )
    const criarUrl = vi.fn((_objeto: Blob | MediaSource): string => 'blob:csv')
    vi.spyOn(URL, 'createObjectURL').mockImplementation(criarUrl)
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(vi.fn())

    render(<Relatorios contexto={escopo} />)
    getJson.mockClear()

    await usuario.click(await screen.findByRole('button', { name: 'Abrir Igreja Centro' }))
    await acharTitulo('Igreja Centro (Igreja)')

    await usuario.click(screen.getByRole('button', { name: /exportar csv/i }))

    await waitFor(() => expect(criarUrl).toHaveBeenCalled())
    const chamadaCsv = getJson.mock.calls.find((c) => consultaDe(c).formato === 'csv')
    expect(chamadaCsv).toBeDefined()
    // A exportação pede o nó aberto (a igreja), não a raiz.
    expect(consultaDe(chamadaCsv!)).toMatchObject({ nivel: 'igreja', id: 'ig-1' })
    const blob = criarUrl.mock.calls[0]?.[0] as Blob
    expect(await blob.text()).toBe('Unidade;Presencas\nRegional Sul;40\n')
  })

  it('volta a raiz do novo contexto quando ele muda', async () => {
    const usuario = userEvent.setup()
    const outro: ContextoAtuacao = { papel: 'adm_regional', tipo_escopo: 'regional', escopo_id: 'reg-2' }
    getJson.mockImplementation(async (_caminho: string, opcoes: { params?: { query?: Record<string, string> } }) =>
      resposta(
        opcoes.params?.query?.id === 'ig-1'
          ? {
              ...relatorio({ nivel: 'igreja', id: 'ig-1', nome: 'Igreja Centro' }),
              filhos: [],
            }
          : { ...relatorio(), filhos: [filho('ig-1', 'Igreja Centro')] },
      ),
    )

    const { rerender } = render(<Relatorios contexto={escopo} />)
    await usuario.click(await screen.findByRole('button', { name: 'Abrir Igreja Centro' }))
    await acharTitulo('Igreja Centro (Igreja)')

    rerender(<Relatorios contexto={outro} />)

    // O novo contexto recomeça na raiz: a igreja aberta some e a trilha zera.
    await acharTitulo('Regional Sul (Regional)')
    const trilha = screen.getByRole('navigation', { name: /trilha/i })
    expect(within(trilha).queryByRole('button', { name: 'Igreja Centro' })).not.toBeInTheDocument()
    expect(consultaDe(getJson.mock.calls.at(-1)!)).not.toHaveProperty('id')
  })

  it('recarrega a unidade aberta ao aplicar um novo periodo', async () => {
    const usuario = userEvent.setup()
    getJson.mockImplementation(async (_caminho: string, opcoes: { params?: { query?: Record<string, string> } }) => {
      const { id, inicio } = opcoes.params?.query ?? {}
      return resposta({
        ...(id === 'ig-1' ? relatorio({ nivel: 'igreja', id: 'ig-1', nome: 'Igreja Centro' }) : relatorio()),
        periodo: { inicio: inicio ?? '', fim: '2026-11-19' },
        filhos: id === undefined ? [filho('ig-1', 'Igreja Centro')] : [],
      })
    })

    render(<Relatorios contexto={escopo} />)
    await usuario.click(await screen.findByRole('button', { name: 'Abrir Igreja Centro' }))
    await acharTitulo('Igreja Centro (Igreja)')
    getJson.mockClear()

    const inicio = screen.getByLabelText('Início')
    await usuario.clear(inicio)
    await usuario.type(inicio, '2026-10-01')
    await usuario.click(screen.getByRole('button', { name: /aplicar período/i }))

    // A tela continua na igreja aberta: título e trilha não voltam à raiz.
    await waitFor(() =>
      expect(getJson.mock.calls.some((c) => consultaDe(c).inicio === '2026-10-01')).toBe(true),
    )
    const comNovoPeriodo = getJson.mock.calls.filter((c) => consultaDe(c).inicio === '2026-10-01')
    expect(consultaDe(comNovoPeriodo.at(-1)!)).toMatchObject({
      nivel: 'igreja',
      id: 'ig-1',
      inicio: '2026-10-01',
    })
    expect(await acharTitulo('Igreja Centro (Igreja)')).toBeInTheDocument()
    const trilha = screen.getByRole('navigation', { name: /trilha/i })
    expect(within(trilha).getByRole('button', { name: 'Igreja Centro' })).toBeInTheDocument()
  })
})
