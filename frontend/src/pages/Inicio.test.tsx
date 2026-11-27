import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
  },
}))

import { Inicio } from './Inicio'

const usuario = { id: 'u1', nome: 'Carla Souza', email: 'carla@x.com', papeis: ['coordenadora'], mfa_ativo: true }

function renderizar(papel: string, tipo_escopo: 'ebi' | 'igreja' = 'ebi', onSelecionarTela = vi.fn()) {
  render(
    <Inicio
      usuario={usuario}
      escopo={{ papel, tipo_escopo, escopo_id: 'E1' }}
      onSelecionarTela={onSelecionarTela}
    />,
  )
  return onSelecionarTela
}

const chamouAlertas = () => getJson.mock.calls.some(([rota]) => rota === '/ebis/{id}/age-alerts')

describe('bloco Fazem 12 anos no Início', () => {
  beforeEach(() => getJson.mockReset())

  it.each(['coordenadora', 'coordenadora_chefe'])('CT-3 %s ve a lista e vai para a exceção', async (papel) => {
    getJson.mockResolvedValue({
      data: { data: [{ participante_id: 'p1', nome: 'Ana Lima', matricula: 'M-1', faz_12_em: '2026-12-10' }] },
    })
    const usuarioEvt = userEvent.setup()

    const onSelecionarTela = renderizar(papel)

    const card = await screen.findByTestId('aniversarios-12')
    expect(await screen.findByText('Ana Lima')).toBeInTheDocument()
    expect(card).toHaveTextContent('10/12/2026')
    const [, opcoes] = getJson.mock.calls[0]
    expect(opcoes.params).toEqual({ path: { id: 'E1' }, query: { finalidade: 'aviso_12_anos' } })

    await usuarioEvt.click(screen.getByRole('button', { name: 'Exceção inclusiva' }))
    expect(onSelecionarTela).toHaveBeenCalledWith('excecao')
  })

  it('CT-3 lista vazia', async () => {
    getJson.mockResolvedValue({ data: { data: [] } })
    renderizar('coordenadora')
    expect(await screen.findByText('Ninguém faz 12 anos nos próximos 30 dias.')).toBeInTheDocument()
  })

  it.each([
    ['auxiliar', 'ebi'],
    ['coordenadora_chefe', 'igreja'],
  ] as const)('CT-3 %s em escopo %s não vê o bloco', (papel, tipo) => {
    renderizar(papel, tipo)
    expect(screen.queryByTestId('aniversarios-12')).not.toBeInTheDocument()
    expect(chamouAlertas()).toBe(false)
  })

  it('CT-4 erro mostra a mensagem e mantém o resto do Início', async () => {
    getJson.mockResolvedValue({ error: { status: 403 } })
    renderizar('coordenadora')
    expect(await screen.findByText('Não foi possível carregar os aniversários.')).toBeInTheDocument()
    expect(screen.getByText('Acesso rápido')).toBeInTheDocument()
  })
})
