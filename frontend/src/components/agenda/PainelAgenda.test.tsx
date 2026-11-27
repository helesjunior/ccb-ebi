import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../api/client', () => ({
  api: { GET: () => Promise.resolve({ data: { data: [] } }) },
}))

import { PainelAgenda } from './PainelAgenda'

describe('PainelAgenda', () => {
  it('limita o motivo da excecao a 3..247 caracteres', async () => {
    render(<PainelAgenda ebiId="E1" podeGerenciar />)
    await userEvent.click(await screen.findByRole('tab', { name: 'Exceções' }))
    fireEvent.change(screen.getByLabelText('Data'), { target: { value: '2026-12-25' } })

    const motivo = screen.getByLabelText(/3 a 247 caracteres/)
    expect(motivo).toHaveAttribute('maxLength', '247')
    expect(motivo).toHaveAttribute('minLength', '3')

    const criar = screen.getByRole('button', { name: 'Criar exceção' })
    fireEvent.change(motivo, { target: { value: 'ab' } })
    expect(criar).toBeDisabled()
    fireEvent.change(motivo, { target: { value: 'abc' } })
    expect(criar).toBeEnabled()
  })
})
