import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
  },
}))

import { Participantes } from './Participantes'

/** Resposta do `/participants` suprimida pelo limiar (ADR-0020). */
function suprimida() {
  return { data: { data: [], suprimido: true, motivo: 'limiar' } }
}

describe('busca de participantes', () => {
  it('avisa que a lista foi suprimida em vez de dizer que ninguem existe', async () => {
    const usuario = userEvent.setup()
    getJson.mockResolvedValue(suprimida())

    render(<Participantes />)
    await usuario.type(screen.getByLabelText('Nome'), 'Ana')
    await usuario.click(screen.getByRole('button', { name: /buscar/i }))

    expect(await screen.findByText(/não divulga/i)).toBeInTheDocument()
    expect(screen.queryByText(/ninguém encontrado/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/0 participantes encontrados/i)).not.toBeInTheDocument()
  })
})
