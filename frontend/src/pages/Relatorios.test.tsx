import { render, screen, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
  },
}))

import { Relatorios } from './Relatorios'
import { textoIndicador, explicacaoIndicador, periodoPadrao } from './relatorios/indicadores'
import type { ContextoAtuacao } from '../api/sessao'
import type { components } from '../api/schema'

type Motivo = components['schemas']['RelatorioIndicador']['motivo']

const escopo: ContextoAtuacao = { papel: 'adm_regional', tipo_escopo: 'regional', escopo_id: 'reg-1' }

function indicador(valor: number | null, suprimido = false, motivo: Motivo = null) {
  return { valor, suprimido, motivo }
}

function relatorio(no: Partial<{ nivel: string; id: string; nome: string }> = {}, totais = {}) {
  return {
    no: { nivel: 'regional', id: 'reg-1', nome: 'Regional Sul', ...no },
    periodo: { inicio: '2026-10-21', fim: '2026-11-19' },
    fuso: 'America/Sao_Paulo',
    gerado_em: '2026-11-19T12:00:00+00:00',
    criterios: 'Participantes sem igreja de referencia nao entram.',
    totais: {
      participantes_distintos: indicador(12),
      presencas: indicador(40),
      sessoes_realizadas: indicador(4),
      media_por_sessao: indicador(10),
      participantes_cadastrados: indicador(30),
      participantes_ativos: indicador(25),
      igrejas_com_ebi_ativo: indicador(3),
      sessoes_previstas: indicador(6),
      sessoes_com_equipe_minima: indicador(3),
      cobertura_escala: indicador(50),
      ...totais,
    },
    filhos: [],
  }
}

describe('rotulos dos indicadores', () => {
  it('mostra < 5 para o limiar', () => {
    expect(textoIndicador(indicador(null, true, 'limiar'))).toBe('< 5')
  })

  it('mostra Nao divulgado com a explicacao para filho suprimido', () => {
    expect(textoIndicador(indicador(null, true, 'filho_suprimido'))).toBe('Não divulgado')
    expect(explicacaoIndicador(indicador(null, true, 'filho_suprimido'))).toBe(
      'uma unidade abaixo tem menos de 5',
    )
  })

  it('mostra — para nao se aplica', () => {
    expect(textoIndicador(indicador(null, false, 'nao_se_aplica'))).toBe('—')
  })
})

describe('periodo', () => {
  it('usa 30 dias por padrao', () => {
    expect(periodoPadrao('2026-11-19')).toEqual({ inicio: '2026-10-21', fim: '2026-11-19' })
  })
})

describe('tela de relatorios', () => {
  it('carrega o no do contexto e mostra totais, filhos e rodape', async () => {
    getJson.mockResolvedValue({
      data: {
        ...relatorio({}, { participantes_distintos: indicador(null, true, 'limiar') }),
        filhos: [
          {
            nivel: 'igreja',
            id: 'ig-1',
            nome: 'Igreja Centro',
            totais: {
              participantes_distintos: indicador(null, true, 'limiar'),
              presencas: indicador(null, true, 'filho_suprimido'),
              sessoes_realizadas: indicador(2),
              media_por_sessao: indicador(null, true, 'filho_suprimido'),
              participantes_cadastrados: indicador(null, false, 'nao_se_aplica'),
              participantes_ativos: indicador(9),
              igrejas_com_ebi_ativo: indicador(1),
            },
          },
        ],
      },
    })

    render(<Relatorios contexto={escopo} />)

    const linha = await screen.findByRole('row', { name: /Igreja Centro/ })
    expect(within(linha).getByText('< 5')).toBeInTheDocument()
    expect(within(linha).getAllByText('Não divulgado')[0]).toBeInTheDocument()
    expect(screen.getByText(/America\/Sao_Paulo/)).toBeInTheDocument()
    expect(screen.getByText(/gerado em/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /exportar csv/i })).toBeInTheDocument()
  })

  it('mostra os tres indicadores da cobertura da escala (M-06)', async () => {
    getJson.mockResolvedValue({
      data: {
        ...relatorio(),
        totais: {
          ...relatorio().totais,
          cobertura_escala: indicador(66.7),
          sessoes_com_equipe_minima: indicador(2),
        },
      },
    })

    render(<Relatorios contexto={escopo} />)

    const bloco = (await screen.findByRole('heading', { name: 'Cobertura da escala' })).parentElement!
    expect(within(bloco).getAllByRole('term').map((item) => item.textContent)).toEqual([
      'Sessões previstas',
      'Sessões com equipe mínima',
      'Cobertura da escala (%)',
    ])
    expect(within(bloco).getByText('66,7')).toBeInTheDocument()
  })

  it('cobertura sem sessoes previstas aparece como —', async () => {
    getJson.mockResolvedValue({
      data: {
        ...relatorio(),
        totais: {
          ...relatorio().totais,
          sessoes_previstas: indicador(0),
          sessoes_com_equipe_minima: indicador(0),
          cobertura_escala: indicador(null),
        },
      },
    })

    render(<Relatorios contexto={escopo} />)

    const bloco = (await screen.findByRole('heading', { name: 'Cobertura da escala' })).parentElement!
    expect(within(bloco).getByText('—')).toBeInTheDocument()
  })
})
