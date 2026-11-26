import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const get = vi.fn(() => Promise.resolve({ data: { data: [] } }))

vi.mock('../api/client', () => ({
  api: { GET: (...args: unknown[]) => get(...(args as [])) },
}))

import { HistoricoPresencas } from './HistoricoPresencas'

describe('historico de presencas', () => {
  it('P2 operador envia finalidade consulta_historico; responsavel nao envia', async () => {
    const { unmount } = render(<HistoricoPresencas participanteId="p-1" rota="operador" />)
    fireEvent.click(screen.getByRole('button', { name: /Histórico/i }))
    await waitFor(() => expect(get).toHaveBeenCalledWith('/participants/{id}/attendance', expect.objectContaining({
      params: { path: { id: 'p-1' }, query: { finalidade: 'consulta_historico' } },
    })))
    unmount()

    render(<HistoricoPresencas participanteId="p-1" rota="responsavel" />)
    fireEvent.click(screen.getByRole('button', { name: /Histórico/i }))
    await waitFor(() => expect(get).toHaveBeenCalledWith('/me/participants/{id}/attendance', expect.objectContaining({
      params: { path: { id: 'p-1' } },
    })))
  })
})
