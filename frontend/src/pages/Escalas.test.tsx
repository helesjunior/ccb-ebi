import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const post = vi.fn()
const patch = vi.fn()
const del = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => get(...args),
    POST: (...args: unknown[]) => post(...args),
    PATCH: (...args: unknown[]) => patch(...args),
    DELETE: (...args: unknown[]) => del(...args),
  },
}))

import { Escalas } from './Escalas'
import type { ContextoAtuacao } from '../api/sessao'

const contexto: ContextoAtuacao = { papel: 'coordenadora', tipo_escopo: 'ebi', escopo_id: 'ebi-1' }

const resumo = (situacao: string, extras = {}) => ({
  sessao_id: 'ses-1',
  inicio_previsto: '2026-12-05T23:00:00Z',
  fim_previsto: '2026-12-06T01:00:00Z',
  sessao_status: 'prevista',
  escala_id: situacao === 'sem_escala' ? null : 'esc-1',
  situacao,
  confirmados: 0,
  membros: 0,
  ...extras,
})

const detalhe = (extras = {}) => ({
  id: 'esc-1',
  sessao_id: 'ses-1',
  status: 'prevista',
  situacao: 'abaixo_do_minimo',
  estimativa_participantes: 8,
  participantes_por_auxiliar: 4,
  sessao: { inicio_previsto: '2026-12-05T23:00:00Z', fim_previsto: '2026-12-06T01:00:00Z', status: 'prevista' },
  equipe_minima: {
    estimativa: 8,
    participantes_por_auxiliar: 4,
    coordenadoras_necessarias: 1,
    auxiliares_necessarios: 2,
    coordenadoras_escaladas: 1,
    auxiliares_escalados: 0,
    atingida: false,
  },
  membros: [
    { id: 'm-1', usuario_id: 'u-1', nome: 'Ana', funcao: 'coordenadora', status: 'escalado', conflitos: [] },
    {
      id: 'm-2',
      usuario_id: 'u-2',
      nome: 'Bia',
      funcao: 'auxiliar',
      status: 'escalado',
      conflitos: [{ sessao_id: null, inicio: '2026-12-05T23:30:00Z', fim: '2026-12-06T00:30:00Z', igreja_nome: null, ebi_nome: null }],
    },
  ],
  presenca_equipe_pendente: false,
  ...extras,
})

function responderResumo(situacao: string) {
  get.mockImplementation((rota: string) => {
    if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
    if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo(situacao)] } })
    if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: detalhe() } })
    if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
    if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
    return Promise.resolve({ error: { status: 404, detail: 'x' } })
  })
}

describe('tela de escalas', () => {
  it('mostra a situacao de cada sessao', async () => {
    responderResumo('sem_escala')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} />)
    expect(await screen.findByText('Sem escala')).toBeInTheDocument()
  })

  it('mostra abaixo do minimo e aguardando aprovacao', async () => {
    responderResumo('abaixo_do_minimo')
    const { unmount } = render(<Escalas contexto={contexto} papeis={['coordenadora']} />)
    expect(await screen.findByText('Abaixo do mínimo')).toBeInTheDocument()
    unmount()

    responderResumo('aguardando_aprovacao')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} />)
    expect(await screen.findByText('Aguardando aprovação')).toBeInTheDocument()
  })

  it('Aprovar so aparece para a chefe', async () => {
    responderResumo('aguardando_aprovacao')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} />)
    expect(await screen.findByText('Aguardando aprovação')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }))
    await screen.findByText('Ana')
    expect(screen.queryByRole('button', { name: /^aprovar$/i })).not.toBeInTheDocument()
  })

  it('a chefe aprova pelo detalhe', async () => {
    responderResumo('aguardando_aprovacao')
    post.mockResolvedValue({ data: { escala: detalhe({ status: 'aprovada', situacao: 'aprovada' }) } })
    render(<Escalas contexto={contexto} papeis={['coordenadora_chefe']} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    const aprovar = await screen.findByRole('button', { name: /^aprovar$/i })
    fireEvent.click(aprovar)
    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedules/{id}/approve', expect.anything()))
  })

  it('conflito fora do territorio aparece como outra igreja', async () => {
    responderResumo('abaixo_do_minimo')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    expect(await screen.findByText(/outra igreja/i)).toBeInTheDocument()
  })
})
