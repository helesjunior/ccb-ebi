import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const post = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => get(...args),
    POST: (...args: unknown[]) => post(...args),
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
})
