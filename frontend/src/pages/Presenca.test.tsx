import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const put = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => get(...args),
    PUT: (...args: unknown[]) => put(...args),
    POST: () => Promise.resolve({ data: null }),
  },
}))

import { Presenca } from './Presenca'

const sessao = (status: string, extras = {}) => ({
  id: 'ses-1',
  ebi_nome: 'EBI Central',
  inicio_previsto: '2026-11-20T23:00:00Z',
  fim_previsto: '2026-11-21T01:00:00Z',
  status,
  timezone: 'America/Sao_Paulo',
  equipe_minima: {
    presentes: 10,
    auxiliares_necessarios: 2,
    auxiliares_informados: 22,
    alerta: false,
  },
  ...extras,
})

const escala = (extras = {}) => ({
  id: 'esc-1',
  sessao_id: 'ses-1',
  status: 'aprovada',
  pode_gerir: true,
  presenca_equipe_pendente: false,
  membros: [
    { id: 'm-1', nome: 'Ana', funcao: 'coordenadora', status: 'confirmado', presente: null },
    { id: 'm-2', nome: 'Bia', funcao: 'auxiliar', status: 'escalado', presente: null },
  ],
  ...extras,
})

function responder(status: string, dadosEscala: unknown, extrasSessao = {}) {
  get.mockImplementation((rota: string) => {
    if (rota === '/sessions/{id}') return Promise.resolve({ data: sessao(status, { escala_id: 'esc-1', ...extrasSessao }) })
    if (rota === '/sessions/{id}/attendance') return Promise.resolve({ data: { data: [] } })
    if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: dadosEscala } })
    return Promise.resolve({ error: { status: 404, detail: 'x' } })
  })
}

describe('finalidade', () => {
  it('P1 envia finalidade registro_presenca na lista da sessao', async () => {
    responder('aberta', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    await waitFor(() => expect(get).toHaveBeenCalledWith('/sessions/{id}/attendance', expect.objectContaining({
      params: { path: { id: 'ses-1' }, query: { finalidade: 'registro_presenca' } },
    })))
  })
})

describe('presenca da equipe', () => {
  it('nao aparece com a sessao aberta', async () => {
    responder('aberta', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    expect(await screen.findByText(/sessão|Presença/i)).toBeInTheDocument()
    await waitFor(() => expect(get).toHaveBeenCalled())
    expect(screen.queryByText('Presença da equipe')).not.toBeInTheDocument()
  })

  it('com a sessao encerrada, a caixa vem marcada para quem confirmou', async () => {
    responder('encerrada', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    const ana = await screen.findByRole('checkbox', { name: /Esteve: Ana/i })
    const bia = screen.getByRole('checkbox', { name: /Esteve: Bia/i })
    expect(ana).toBeChecked()
    expect(bia).not.toBeChecked()
  })

  it('avisa que a presenca da equipe nao foi registrada', async () => {
    responder('encerrada', escala({ presenca_equipe_pendente: true }))
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora_chefe']} />)
    expect(await screen.findByText('Presença da equipe não registrada.')).toBeInTheDocument()
  })

  it('envia o que foi marcado pelo team-attendance', async () => {
    responder('encerrada', escala())
    put.mockResolvedValue({ data: { escala: escala() } })
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('checkbox', { name: /Esteve: Bia/i }))
    fireEvent.click(screen.getByRole('button', { name: /registrar presença da equipe/i }))
    await waitFor(() => expect(put).toHaveBeenCalledWith('/schedules/{id}/team-attendance', expect.anything()))
    const corpo = put.mock.calls[0][1].body
    expect(corpo.membros).toEqual([
      { id: 'm-1', presente: true },
      { id: 'm-2', presente: true },
    ])
  })

  it('erro no envio mantem o formulario', async () => {
    responder('encerrada', escala())
    put.mockResolvedValue({ error: { status: 422, code: 'membro_invalido', detail: 'Vaga inválida.' } })
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('button', { name: /registrar presença da equipe/i }))
    expect(await screen.findByText('Vaga inválida.')).toBeInTheDocument()
    expect(screen.getByRole('checkbox', { name: /Esteve: Ana/i })).toBeInTheDocument()
  })

  it('busca a escala por sessao.escala_id', async () => {
    responder('encerrada', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    await screen.findByRole('checkbox', { name: /Esteve: Ana/i })
    const rotasDaEscala = get.mock.calls.filter(([rota]) => rota === '/schedules/{id}')
    expect(rotasDaEscala.length).toBeGreaterThan(0)
    for (const chamada of rotasDaEscala) {
      expect(chamada[1].params.path.id).toBe('esc-1')
    }
  })

  it('sem escala_id nao mostra o bloco da equipe', async () => {
    responder('encerrada', escala(), { escala_id: null })
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    expect(await screen.findByText(/Presenças?/i)).toBeInTheDocument()
    await waitFor(() => expect(get).toHaveBeenCalledWith('/sessions/{id}', expect.anything()))
    expect(screen.queryByText('Presença da equipe')).not.toBeInTheDocument()
    expect(get.mock.calls.some(([rota]) => rota === '/schedules/{id}')).toBe(false)
  })

  it('leitora nao dona ve a equipe so em leitura', async () => {
    responder('encerrada', escala({ pode_gerir: false }))
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora_chefe']} />)
    expect(await screen.findByRole('checkbox', { name: /Esteve: Ana/i })).toBeDisabled()
    expect(screen.queryByRole('button', { name: /registrar presença da equipe/i })).not.toBeInTheDocument()
  })

  it('recarrega a equipe apos 422 membro_invalido mantendo marcacoes', async () => {
    responder('encerrada', escala())
    put.mockResolvedValue({ error: { status: 422, code: 'membro_invalido', detail: 'Vaga inválida.' } })
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('checkbox', { name: /Esteve: Bia/i }))
    const antes = get.mock.calls.filter(([rota]) => rota === '/schedules/{id}').length
    fireEvent.click(screen.getByRole('button', { name: /registrar presença da equipe/i }))
    expect(await screen.findByText('Vaga inválida.')).toBeInTheDocument()
    await waitFor(() => {
      expect(get.mock.calls.filter(([rota]) => rota === '/schedules/{id}').length).toBeGreaterThan(antes)
    })
    expect(screen.getByRole('checkbox', { name: /Esteve: Bia/i })).toBeChecked()
    expect(screen.getByRole('checkbox', { name: /Esteve: Ana/i })).toBeChecked()
  })
})
