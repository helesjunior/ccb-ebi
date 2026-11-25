import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const post = vi.fn()
const del = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => get(...args),
    POST: (...args: unknown[]) => post(...args),
    DELETE: (...args: unknown[]) => del(...args),
  },
}))

import { MinhasEscalas } from './MinhasEscalas'

const minha = (extras = {}) => ({
  escala_id: 'esc-1',
  membro_id: 'm-1',
  sessao_id: 'ses-1',
  inicio_previsto: '2026-12-05T23:00:00Z',
  fim_previsto: '2026-12-06T01:00:00Z',
  sessao_status: 'prevista',
  escala_status: 'prevista',
  funcao: 'auxiliar',
  status: 'escalado',
  presente: null,
  conflitos: [],
  ...extras,
})

function responder(lista: unknown[]) {
  get.mockResolvedValue({ data: { data: lista } })
}

describe('minhas escalas', () => {
  it('escala prevista nao mostra Confirmar', async () => {
    responder([minha()])
    render(<MinhasEscalas />)
    expect(await screen.findByText(/auxiliar/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^confirmar$/i })).not.toBeInTheDocument()
  })

  it('escala aprovada mostra o conflito antes do botao Confirmar', async () => {
    responder([
      minha({
        escala_status: 'aprovada',
        conflitos: [{ sessao_id: 'ses-2', inicio: '2026-12-05T23:30:00Z', fim: '2026-12-06T00:30:00Z', igreja_nome: 'Igreja Centro', ebi_nome: 'EBI Norte' }],
      }),
    ])
    render(<MinhasEscalas />)
    const conflito = await screen.findByText(/conflito/i)
    const confirmar = screen.getByRole('button', { name: /^confirmar$/i })
    expect(conflito.compareDocumentPosition(confirmar) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('presenca nula aparece como nao registrado', async () => {
    responder([minha({
      escala_status: 'aprovada',
      sessao_status: 'encerrada',
      inicio_previsto: '2026-01-10T23:00:00Z',
      fim_previsto: '2026-01-11T01:00:00Z',
      presente: null,
    })])
    render(<MinhasEscalas />)
    expect(await screen.findByText(/não registrado/i)).toBeInTheDocument()
  })

  it('confirma a vaga pela API', async () => {
    responder([minha({ escala_status: 'aprovada' })])
    post.mockResolvedValue({ data: { membro: { id: 'm-1', status: 'confirmado' }, conflitos: [] } })
    render(<MinhasEscalas />)
    fireEvent.click(await screen.findByRole('button', { name: /^confirmar$/i }))
    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedule-members/{id}/confirm', expect.anything()))
  })

  const vagaAberta = (extras = {}) => ({
    id: 'vg-1',
    tipo: 'acrescimo',
    status: 'aberta',
    ebi_nome: 'EBI Central',
    observacao: 'Precisamos de alguém',
    ofertas_pendentes: 2,
    minha_oferta: null,
    conflitos: [],
    sessao: { id: 'ses-1', inicio_previsto: '2026-12-05T23:00:00Z', fim_previsto: '2026-12-06T01:00:00Z' },
    ...extras,
  })

  /** Rota das vagas abertas para a propria pessoa. */
  function responderVagas(vagas: unknown[]) {
    get.mockImplementation((rota: string) => {
      if (rota === '/me/schedule-vacancies') return Promise.resolve({ data: { data: vagas } })
      return Promise.resolve({ data: { data: [] } })
    })
  }

  it('lista vagas abertas e permite oferecer-se', async () => {
    responderVagas([vagaAberta()])
    // Depois de oferecer, a recarga ja traz a propria oferta.
    let ofereceu = false
    get.mockImplementation((rota: string) => {
      if (rota === '/me/schedule-vacancies') {
        return Promise.resolve({ data: { data: [vagaAberta(ofereceu ? { minha_oferta: { id: 'of-1', status: 'pendente' } } : {})] } })
      }
      return Promise.resolve({ data: { data: [] } })
    })
    post.mockImplementation(() => {
      ofereceu = true
      return Promise.resolve({ data: { data: vagaAberta({ minha_oferta: { id: 'of-1', status: 'pendente' } }) } })
    })
    render(<MinhasEscalas />)

    expect(await screen.findByText(/vagas abertas/i)).toBeInTheDocument()
    expect(screen.getByText('Precisamos de alguém')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /oferecer-se/i }))

    await waitFor(() => expect(post).toHaveBeenCalledWith('/schedule-vacancies/{id}/offers', expect.objectContaining({
      params: { path: { id: 'vg-1' } },
    })))
    expect(await screen.findByText(/você se ofereceu/i)).toBeInTheDocument()
  })

  it('mostra minha oferta e permite retirar', async () => {
    responderVagas([vagaAberta({ minha_oferta: { id: 'of-1', status: 'pendente' } })])
    del.mockResolvedValue({ data: {} })
    render(<MinhasEscalas />)

    expect(await screen.findByText(/você se ofereceu/i)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /retirar oferta/i }))

    await waitFor(() => expect(del).toHaveBeenCalledWith('/schedule-vacancy-offers/{id}', expect.objectContaining({
      params: { path: { id: 'of-1' } },
    })))
  })

  it('mostra o conflito de horario devolvido pelo servidor', async () => {
    responderVagas([vagaAberta()])
    post.mockResolvedValue({
      error: {
        status: 422,
        code: 'conflito_de_horario',
        detail: 'Você já tem outra atividade neste horário.',
      },
    })
    render(<MinhasEscalas />)

    fireEvent.click(await screen.findByRole('button', { name: /oferecer-se/i }))
    expect(await screen.findByText(/já tem outra atividade neste horário/i)).toBeInTheDocument()
  })
})
