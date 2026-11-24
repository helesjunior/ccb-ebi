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

const sessao = (status: string) => ({
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
})

const escala = (extras = {}) => ({
  id: 'esc-1',
  sessao_id: 'ses-1',
  status: 'aprovada',
  presenca_equipe_pendente: false,
  membros: [
    { id: 'm-1', nome: 'Ana', funcao: 'coordenadora', status: 'confirmado', presente: null },
    { id: 'm-2', nome: 'Bia', funcao: 'auxiliar', status: 'escalado', presente: null },
  ],
  ...extras,
})

function responder(status: string, dadosEscala: unknown) {
  get.mockImplementation((rota: string) => {
    if (rota === '/sessions/{id}') return Promise.resolve({ data: sessao(status) })
    if (rota === '/sessions/{id}/attendance') return Promise.resolve({ data: { data: [] } })
    if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: dadosEscala } })
    return Promise.resolve({ error: { status: 404, detail: 'x' } })
  })
}

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
})
