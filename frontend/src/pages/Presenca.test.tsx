import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const put = vi.fn()
const post = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => get(...args),
    PUT: (...args: unknown[]) => put(...args),
    POST: (...args: unknown[]) => post(...args),
  },
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

import { toast } from 'sonner'
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

const candidatos = (busca?: string) => ({
  Ana: [{ id: 'p-ana', nome: 'Ana Clara', matricula: 'M1', elegivel: true }],
  Bia: [{ id: 'p-bia', nome: 'Bia Lima', matricula: 'M2', elegivel: true }],
})[busca ?? ''] ?? []

function responder(status: string, dadosEscala: unknown, extrasSessao = {}) {
  get.mockImplementation((rota: string, opcoes?: { params?: { query?: { busca?: string } } }) => {
    if (rota === '/sessions/{id}') return Promise.resolve({ data: sessao(status, { escala_id: 'esc-1', ...extrasSessao }) })
    if (rota === '/sessions/{id}/attendance') return Promise.resolve({ data: { data: [] } })
    if (rota === '/schedules/{id}') return Promise.resolve({ data: { escala: dadosEscala } })
    if (rota === '/sessions/{id}/participants') return Promise.resolve({ data: { data: candidatos(opcoes?.params?.query?.busca) } })
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

describe('contingencia', () => {
  it('limita o motivo a 255 caracteres', async () => {
    responder('encerrada', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('button', { name: 'Lançar lista em papel' }))
    const motivo = screen.getByLabelText('Motivo da contingência (3 a 255 caracteres)')
    expect(motivo).toHaveAttribute('maxLength', '255')
  })

  async function marcarAnaEBia(motivo = 'lista em papel') {
    responder('encerrada', escala())
    render(<Presenca sessaoId="ses-1" onVoltar={() => {}} papeis={['coordenadora']} />)
    fireEvent.click(await screen.findByRole('button', { name: 'Lançar lista em papel' }))
    fireEvent.change(screen.getByLabelText('Motivo da contingência (3 a 255 caracteres)'), { target: { value: motivo } })
    const busca = screen.getByLabelText('Nome ou matrícula')
    fireEvent.change(busca, { target: { value: 'Ana' } })
    fireEvent.click(await screen.findByRole('button', { name: /Ana Clara/ }))
    fireEvent.change(busca, { target: { value: 'Bia' } })
    fireEvent.click(await screen.findByRole('button', { name: /Bia Lima/ }))
  }

  it('CT-32 lanca os marcados num unico POST', async () => {
    post.mockResolvedValue({
      data: {
        data: [{ participante_id: 'p-ana', resultado: 'criada' }, { participante_id: 'p-bia', resultado: 'ja_existia' }],
        resumo: { criadas: 1, ja_existiam: 1, erros: 0 },
      },
    })
    await marcarAnaEBia()
    expect(screen.getByTestId('contingencia-marcados')).toHaveTextContent('Ana Clara')

    fireEvent.click(screen.getByRole('button', { name: 'Lançar 2 presenças' }))

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('1 lançadas, 1 já existiam'))
    expect(post).toHaveBeenCalledTimes(1)
    expect(post).toHaveBeenCalledWith('/sessions/{id}/contingency-attendance/batch', expect.objectContaining({
      body: { participante_ids: ['p-ana', 'p-bia'], motivo: 'lista em papel' },
    }))
    expect(screen.queryByTestId('contingencia-marcados')).not.toBeInTheDocument()
  })

  it('CT-33 linha com erro lista quem nao foi lancado', async () => {
    post.mockResolvedValue({
      data: {
        data: [{ participante_id: 'p-ana', resultado: 'criada' }, { participante_id: 'p-bia', resultado: 'erro', erro: 'inelegivel' }],
        resumo: { criadas: 1, ja_existiam: 0, erros: 1 },
      },
    })
    await marcarAnaEBia()
    fireEvent.click(screen.getByRole('button', { name: 'Lançar 2 presenças' }))
    expect(await screen.findByText('Não lançadas: Bia Lima')).toBeInTheDocument()
  })

  it('CT-33 409 mostra o detail', async () => {
    post.mockResolvedValue({ error: { status: 409, code: 'sessao_em_estado_invalido', detail: 'Sessão não está encerrada.' } })
    await marcarAnaEBia()
    fireEvent.click(screen.getByRole('button', { name: 'Lançar 2 presenças' }))
    expect(await screen.findByText('Sessão não está encerrada.')).toBeInTheDocument()
  })

  it('CT-33 motivo curto desabilita o lancamento', async () => {
    await marcarAnaEBia('ab')
    expect(screen.getByRole('button', { name: 'Lançar 2 presenças' })).toBeDisabled()
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
