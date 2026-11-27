import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getJson = vi.fn()
const patch = vi.fn()

vi.mock('../api/client', () => ({
  api: {
    GET: (...args: unknown[]) => getJson(...args),
    PATCH: (...args: unknown[]) => patch(...args),
  },
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

import { toast } from 'sonner'
import { Participantes } from './Participantes'

/** Resposta do `/participants` suprimida pelo limiar (ADR-0020). */
function suprimida() {
  return { data: { data: [], suprimido: true, motivo: 'limiar' } }
}

describe('busca de participantes', () => {
  it('avisa que a lista foi suprimida em vez de dizer que ninguem existe', async () => {
    const usuario = userEvent.setup()
    getJson.mockResolvedValue(suprimida())

    render(<Participantes escopo={null} />)
    await usuario.type(screen.getByLabelText('Nome'), 'Ana')
    await usuario.click(screen.getByRole('button', { name: /buscar/i }))

    expect(await screen.findByText(/não divulga/i)).toBeInTheDocument()
    expect(screen.queryByText(/ninguém encontrado/i)).not.toBeInTheDocument()
    expect(screen.queryByText(/0 participantes encontrados/i)).not.toBeInTheDocument()
  })
})
const ana = { id: 'p1', nome: 'Ana Lima', nome_social: null, data_nascimento: '2016-05-10', matricula: 'M-1', igreja_referencia_id: 'I1' }

async function buscarComo(papel: string | null) {
  const usuario = userEvent.setup()
  getJson.mockResolvedValue({ data: { data: [ana] } })
  render(<Participantes escopo={papel === null ? null : { papel, tipo_escopo: 'ebi', escopo_id: 'E1' }} />)
  await usuario.type(screen.getByLabelText('Nome'), 'Ana')
  await usuario.click(screen.getByRole('button', { name: /buscar/i }))
  await screen.findByText('Ana Lima')
  return usuario
}

async function corrigirNome(usuario: ReturnType<typeof userEvent.setup>) {
  await usuario.click(screen.getByRole('button', { name: 'Corrigir' }))
  const nome = await screen.findByLabelText('Nome completo')
  await usuario.clear(nome)
  await usuario.type(nome, 'Ana Lima Souza')
  await usuario.type(screen.getByLabelText('Motivo da correção (3 a 255 caracteres)'), 'erro de digitação')
  await usuario.click(screen.getByRole('button', { name: 'Salvar correção' }))
}

describe('correcao de cadastro', () => {
  beforeEach(() => patch.mockReset())

  it('CT-34 manda so o que mudou e atualiza a linha', async () => {
    patch.mockResolvedValue({ data: { ...ana, nome: 'Ana Lima Souza' } })
    const usuario = await buscarComo('coordenadora')

    await corrigirNome(usuario)

    expect(await screen.findByText('Ana Lima Souza')).toBeInTheDocument()
    expect(patch).toHaveBeenCalledWith('/participants/{id}', expect.objectContaining({
      params: { path: { id: 'p1' } },
      body: { nome: 'Ana Lima Souza', motivo: 'erro de digitação' },
    }))
    expect(toast.success).toHaveBeenCalledWith('Cadastro corrigido.')
  })

  it.each([
    [409, 'participante_mesclado', 'Cadastro já foi mesclado.'],
    [422, 'nada_a_corrigir', 'Nada a corrigir.'],
    [422, 'igreja_fora_do_escopo', 'Igreja fora do seu escopo.'],
  ])('CT-35 erro %i %s fica no dialogo', async (status, code, detail) => {
    patch.mockResolvedValue({ error: { status, code, detail } })
    const usuario = await buscarComo('coordenadora_chefe')

    await corrigirNome(usuario)

    expect(await screen.findByText(detail)).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it.each(['auxiliar', 'adm_igreja', null])('CT-36 %s nao ve Corrigir', async (papel) => {
    await buscarComo(papel)
    expect(screen.queryByRole('button', { name: 'Corrigir' })).not.toBeInTheDocument()
  })
})