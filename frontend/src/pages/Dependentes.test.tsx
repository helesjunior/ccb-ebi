import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
  },
}))

import { Dependentes } from './Dependentes'

describe('aviso dos 12 anos em Meus dependentes', () => {
  it('CT-1 avisa so o dependente com faz_12_em', async () => {
    getJson.mockResolvedValue({
      data: {
        data: [
          { id: 'p1', nome: 'Ana Lima', data_nascimento: '2014-12-10', matricula: 'M1', faz_12_em: '2026-12-10' },
          { id: 'p2', nome: 'Bia Lima', data_nascimento: '2016-01-10', matricula: 'M2', faz_12_em: null },
        ],
      },
    })

    render(<Dependentes />)

    const avisos = await screen.findAllByTestId('aviso-12-anos')
    expect(avisos).toHaveLength(1)
    expect(avisos[0]).toHaveTextContent('Ana Lima faz 12 anos em 10/12/2026.')
  })

  it('CT-2 erro mostra a mensagem e nenhum aviso', async () => {
    getJson.mockResolvedValue({ error: { status: 500 } })

    render(<Dependentes />)

    expect(await screen.findByRole('alert')).toHaveTextContent('Não foi possível carregar seus dependentes.')
    expect(screen.queryByTestId('aviso-12-anos')).not.toBeInTheDocument()
  })
})
