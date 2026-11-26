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
const usuario = { id: 'u-coord' }

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
    if (rota === '/ebis/{id}/equipes') {
      return Promise.resolve({ data: { data: [{ id: 'eq-1', coordenadora_id: 'u-1', coordenadora_nome: 'Ana', quantidade_membros: 2 }] } })
    }
    if (rota === '/equipes/{id}') {
      return Promise.resolve({
        data: {
          data: {
            id: 'eq-1', ebi_id: 'ebi-1', coordenadora_id: 'u-1', coordenadora_nome: 'Ana',
            membros: [
              { usuario_id: 'u-2', nome: 'Bia' },
              { usuario_id: 'u-3', nome: 'Caio', sem_vinculo: true },
            ],
          },
        },
      })
    }
    if (rota === '/ebis/{id}/team') {
      return Promise.resolve({
        data: {
          data: [
            { usuario_id: 'u-1', nome: 'Ana', papeis: ['coordenadora'], equipe_id: 'eq-1' },
            { usuario_id: 'u-2', nome: 'Bia', papeis: ['auxiliar'], equipe_id: 'eq-1' },
            { usuario_id: 'u-3', nome: 'Caio', papeis: ['auxiliar'], equipe_id: 'eq-1', sem_vinculo: true },
          ],
        },
      })
    }
    if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
    return Promise.resolve({ error: { status: 404, detail: 'x' } })
  })
}

describe('tela de escalas', () => {
  it('mostra a situacao de cada sessao', async () => {
    responderResumo('sem_escala')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)
    expect(await screen.findByText('Sem escala')).toBeInTheDocument()
  })

  it('mostra abaixo do minimo e aguardando aprovacao', async () => {
    responderResumo('abaixo_do_minimo')
    const { unmount } = render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)
    expect(await screen.findByText('Abaixo do mínimo')).toBeInTheDocument()
    unmount()

    responderResumo('aguardando_aprovacao')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)
    expect(await screen.findByText('Aguardando aprovação')).toBeInTheDocument()
  })

  it('Aprovar so aparece para a chefe', async () => {
    responderResumo('aguardando_aprovacao')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)
    expect(await screen.findByText('Aguardando aprovação')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /detalhe/i }))
    await screen.findByText('Ana')
    expect(screen.queryByRole('button', { name: /^aprovar$/i })).not.toBeInTheDocument()
  })

  it('a chefe aprova pelo detalhe', async () => {
    responderResumo('aguardando_aprovacao')
    post.mockResolvedValue({ data: { escala: detalhe({ status: 'aprovada', situacao: 'aprovada' }) } })
    render(<Escalas contexto={contexto} papeis={['coordenadora_chefe']} usuario={usuario} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    const aprovar = await screen.findByRole('button', { name: /^aprovar$/i })
    fireEvent.click(aprovar)
    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedules/{id}/approve', expect.anything()))
  })

  it('conflito fora do territorio aparece como outra igreja', async () => {
    responderResumo('abaixo_do_minimo')
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    expect(await screen.findByText(/outra igreja/i)).toBeInTheDocument()
  })

  // Fixado pela Task 13.
  it('montar pre-seleciona a dona e os membros vigentes da equipe', async () => {
    responderResumo('sem_escala')
    post.mockResolvedValue({ data: { escala: detalhe({ status: 'prevista', situacao: 'em_montagem' }) } })
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={usuario} />)

    fireEvent.click(await screen.findByRole('button', { name: /^montar$/i }))
    fireEvent.change(await screen.findByLabelText(/estimativa de participantes/i), { target: { value: '8' } })
    fireEvent.click(screen.getByRole('button', { name: /criar escala/i }))

    await waitFor(() => expect(post).toHaveBeenCalled())
    const [, pedido] = post.mock.calls.at(-1) as [string, { body: Record<string, unknown> }]

    expect(pedido.body.equipe_id).toBe('eq-1')
    expect(pedido.body.membros).toEqual([
      { usuario_id: 'u-1', funcao: 'coordenadora' },
      { usuario_id: 'u-2', funcao: 'auxiliar' },
    ])
  })

  it('sem equipe mostra o atalho para criar a equipe', async () => {
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo('sem_escala')] } })
      if (rota === '/ebis/{id}/equipes') return Promise.resolve({ data: { data: [] } })
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={{ id: 'u-1' }} />)

    fireEvent.click(await screen.findByRole('button', { name: /^montar$/i }))
    fireEvent.change(await screen.findByLabelText(/estimativa de participantes/i), { target: { value: '8' } })
    fireEvent.click(screen.getByRole('button', { name: /criar escala/i }))

    expect(await screen.findByText(/equipes de escala/i)).toBeInTheDocument()
    expect(post).not.toHaveBeenCalled()
  })

  it('escrita obedece pode_gerir do detalhe', async () => {
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo('abaixo_do_minimo')] } })
      if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: detalhe(), pode_gerir: false } })
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    // O papel de leitura abre o detalhe, mas o servidor diz que nao gere.
    render(<Escalas contexto={contexto} papeis={['ministerio_local']} usuario={{ id: 'u-1' }} />)

    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    await screen.findByText('Ana')

    expect(screen.queryByRole('button', { name: /retirar/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /salvar estimativa/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^aprovar$/i })).not.toBeInTheDocument()
  })

  it('dona abre vaga avisando que o texto e visivel a todos os auxiliares do EBI', async () => {
    responderResumo('abaixo_do_minimo')
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo('abaixo_do_minimo')] } })
      if (rota === '/schedules/{id}') {
        return Promise.resolve({ data: { escala: detalhe({ vagas: [], pode_escolher_oferta: true, equipe: { id: 'eq-1', coordenadora_id: 'u-1', coordenadora_nome: 'Ana' } }) } })
      }
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-vacancies') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    post.mockResolvedValue({ data: { data: { id: 'vg-1', status: 'aberta', tipo: 'acrescimo' } } })
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={{ id: 'u-1' }} />)

    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    fireEvent.click(await screen.findByRole('button', { name: /abrir vaga/i }))

    expect(await screen.findByText(/visível a todos os auxiliares do EBI/i)).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/observação/i), { target: { value: 'Precisamos de 2 pessoas' } })
    fireEvent.click(screen.getByRole('button', { name: /confirmar vaga/i }))

    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedules/{id}/vacancies', expect.objectContaining({
      params: { path: { id: 'esc-1' } },
      body: { substitui_membro_id: null, observacao: 'Precisamos de 2 pessoas' },
    })))
  })

  it('escolher oferta so aparece com pode_escolher_oferta', async () => {
    const vaga = {
      id: 'vg-1',
      status: 'aberta',
      tipo: 'acrescimo',
      ofertas_pendentes: 2,
      ofertas: [
        { id: 'of-1', usuario_id: 'u-2', nome: 'Bia', status: 'pendente', conflitos: [] },
        { id: 'of-2', usuario_id: 'u-5', nome: 'Dora', status: 'pendente', conflitos: [] },
      ],
    }

    // A chefe supervisa e ve os nomes, mas nao escolhe.
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo('abaixo_do_minimo')] } })
      if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: detalhe({ vagas: [vaga], pode_escolher_oferta: false, pode_gerir: true }) } })
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-vacancies') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    const { unmount } = render(<Escalas contexto={contexto} papeis={['coordenadora_chefe']} usuario={{ id: 'u-chefe' }} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))
    await screen.findByText('Dora')
    expect(screen.queryByRole('button', { name: /escolher/i })).not.toBeInTheDocument()
    unmount()

    // A dona escolhe.
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [resumo('abaixo_do_minimo')] } })
      if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: detalhe({ vagas: [vaga], pode_escolher_oferta: true, pode_gerir: true }) } })
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-vacancies') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-swaps') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    post.mockResolvedValue({ data: { data: { ...vaga, status: 'preenchida' }, membro_id: 'm-9' } })
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={{ id: 'u-1' }} />)
    fireEvent.click(await screen.findByRole('button', { name: /detalhe/i }))

    const escolher = await screen.findAllByRole('button', { name: /^escolher$/i })
    fireEvent.click(escolher.at(-1)!)

    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedule-vacancies/{id}/choose', expect.objectContaining({
      params: { path: { id: 'vg-1' } },
      body: { oferta_id: 'of-2' },
    })))
  })

  it('painel de vagas do EBI lista por data', async () => {
    get.mockImplementation((rota: string) => {
      if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central', igreja_nome: 'Igreja' }] } })
      if (rota === '/ebis/{id}/schedules') return Promise.resolve({ data: { data: [] } })
      if (rota === '/schedule-vacancies') {
        return Promise.resolve({
          data: {
            data: [
              {
                id: 'vg-2', status: 'aberta', tipo: 'substituicao', ebi_nome: 'EBI Central', ofertas_pendentes: 3,
                sessao: { id: 'ses-9', inicio_previsto: '2026-12-20T23:00:00Z', fim_previsto: '2026-12-21T01:00:00Z' },
              },
              {
                id: 'vg-1', status: 'aberta', tipo: 'acrescimo', ebi_nome: 'EBI Central', ofertas_pendentes: 0,
                sessao: { id: 'ses-8', inicio_previsto: '2026-12-06T23:00:00Z', fim_previsto: '2026-12-07T01:00:00Z' },
              },
            ],
          },
        })
      }
      if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: [] } })
      return Promise.resolve({ error: { status: 404, detail: 'x' } })
    })
    render(<Escalas contexto={contexto} papeis={['coordenadora']} usuario={{ id: 'u-1' }} />)

    expect(await screen.findByText(/vagas do EBI/i)).toBeInTheDocument()

    // O servidor entrega a vaga mais proxima primeiro; a tela preserva a ordem.
    const itens = await screen.findAllByRole('listitem')
    expect(itens.length).toBe(2)
    expect(itens[0]).toHaveTextContent('Substituição')
    expect(itens[1]).toHaveTextContent('Acréscimo')
  })
})
