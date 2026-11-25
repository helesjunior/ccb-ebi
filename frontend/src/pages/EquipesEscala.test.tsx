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

import { EquipesEscala } from './EquipesEscala'

const coordenadora = { id: 'u-coord', nome: 'Ana', papeis: ['coordenadora'] }
const contexto = { papel: 'coordenadora', tipo_escopo: 'ebi', escopo_id: 'ebi-1' }

const membro = (usuario_id: string, nome: string, sem_vinculo = false) => ({ usuario_id, nome, sem_vinculo })

function equipe(extras = {}) {
  return {
    id: 'eq-1',
    ebi_id: 'ebi-1',
    igreja_id: 'ig-1',
    coordenadora_id: 'u-coord',
    coordenadora_nome: 'Ana',
    coordenadora_sem_vinculo: false,
    membros: [membro('u-bia', 'Bia'), membro('u-caio', 'Caio', true)],
    ...extras,
  }
}

/** Responde as rotas da tela; `extras` sobrescreve por rota. */
function responder(rotas: {
  equipes?: unknown[]
  detalhe?: unknown
  erroEquipes?: unknown
  erroIncluir?: unknown
  equipeDoEbi?: unknown[]
  coordenadoraDaEquipe?: string
} = {}) {
  get.mockImplementation((rota: string) => {
    if (rota === '/me/ebis') return Promise.resolve({ data: { data: [{ id: 'ebi-1', nome: 'EBI Central' }] } })
    if (rota === '/ebis/{id}/equipes') {
      if (rotas.erroEquipes) return Promise.resolve({ error: rotas.erroEquipes })
      return Promise.resolve({ data: { data: rotas.equipes ?? [{ id: 'eq-1', coordenadora_id: rotas.coordenadoraDaEquipe ?? 'u-coord', coordenadora_nome: 'Ana', quantidade_membros: 2 }] } })
    }
    if (rota === '/equipes/{id}') return Promise.resolve({ data: { data: rotas.detalhe ?? equipe() } })
    if (rota === '/ebis/{id}/team') return Promise.resolve({ data: { data: rotas.equipeDoEbi ?? [] } })
    return Promise.resolve({ error: { status: 404, detail: 'x' } })
  })
}

describe('Equipes de escala', () => {
  it('lista as equipes do EBI com coordenadora sem vinculo marcada', async () => {
    responder({
      equipes: [
        { id: 'eq-1', coordenadora_id: 'u-coord', coordenadora_nome: 'Ana', coordenadora_sem_vinculo: true, quantidade_membros: 2 },
      ],
      detalhe: equipe({ coordenadora_sem_vinculo: true }),
    })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora']} usuario={coordenadora} />)

    expect(await screen.findByText('Ana')).toBeInTheDocument()
    expect((await screen.findAllByText(/coordenadora sem vínculo/i)).length).toBeGreaterThan(0)
  })

  it('coordenadora sem equipe ve "Criar minha equipe"', async () => {
    responder({ equipes: [] })
    post.mockResolvedValue({ data: { data: equipe() } })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora']} usuario={coordenadora} />)

    fireEvent.click(await screen.findByRole('button', { name: /criar minha equipe/i }))

    await waitFor(() => {
      expect(post).toHaveBeenCalledWith('/ebis/{id}/equipes', expect.objectContaining({
        params: { path: { id: 'ebi-1' } },
        body: { coordenadora_id: 'u-coord' },
      }))
    })
  })

  it('inclui membro a partir do GET /ebis/{id}/team', async () => {
    responder({
      equipeDoEbi: [
        { usuario_id: 'u-bia', nome: 'Bia', papeis: ['auxiliar'] },
        { usuario_id: 'u-novo', nome: 'Dora', papeis: ['auxiliar'] },
      ],
    })
    post.mockResolvedValue({ data: { data: equipe({ membros: [membro('u-bia', 'Bia'), membro('u-novo', 'Dora')] }) } })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora']} usuario={coordenadora} />)

    // Bia ja esta na equipe; so Dora aparece como candidata.
    await screen.findByText('Bia')
    const opcao = await screen.findByRole('button', { name: /Dora/i })
    fireEvent.click(opcao)

    await waitFor(() => {
      expect(post).toHaveBeenCalledWith('/equipes/{id}/members', expect.objectContaining({
        params: { path: { id: 'eq-1' } },
        body: { usuario_id: 'u-novo' },
      }))
    })
  })

  it('mostra o erro do servidor ao incluir membro', async () => {
    responder({ equipeDoEbi: [{ usuario_id: 'u-novo', nome: 'Dora', papeis: ['auxiliar'] }] })
    post.mockResolvedValue({
      error: { status: 403, code: 'nao_e_dona_da_equipe', detail: 'Só a dona da equipe edita os membros.' },
    })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora']} usuario={coordenadora} />)

    fireEvent.click(await screen.findByRole('button', { name: /Dora/i }))

    expect(await screen.findByText('Só a dona da equipe edita os membros.')).toBeInTheDocument()
  })

  it('membro sem vinculo aparece marcado', async () => {
    responder({ detalhe: equipe({ membros: [membro('u-bia', 'Bia'), membro('u-caio', 'Caio', true)] }) })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora']} usuario={coordenadora} />)

    const linha = (await screen.findByText('Caio')).closest('tr')
    expect(linha).not.toBeNull()
    expect(linha).toHaveTextContent(/sem vínculo/i)
  })

  it('quem nao e dona ve a equipe sem os botoes de escrita', async () => {
    // A chefe enxerga a equipe da Ana, mas nao a edita: so a dona inclui e retira.
    // Ela ja tem a propria equipe, entao tambem nao ve "Criar minha equipe".
    responder({
      equipeDoEbi: [{ usuario_id: 'u-novo', nome: 'Dora', papeis: ['auxiliar'] }],
      coordenadoraDaEquipe: 'u-coord',
      equipes: [
        { id: 'eq-1', coordenadora_id: 'u-coord', coordenadora_nome: 'Ana', quantidade_membros: 2 },
        { id: 'eq-2', coordenadora_id: 'u-chefe', coordenadora_nome: 'Chefe', quantidade_membros: 0 },
      ],
    })
    render(<EquipesEscala contexto={contexto} papeis={['coordenadora_chefe']} usuario={{ ...coordenadora, id: 'u-chefe', nome: 'Chefe', papeis: ['coordenadora_chefe'] }} />)

    // Espera o detalhe (o cabecalho vem antes de qualquer botao de escrita).
    await screen.findByText(/equipe de ana/i)
    expect(screen.queryByRole('button', { name: /Dora/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /^retirar$/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /criar minha equipe/i })).not.toBeInTheDocument()
  })
})
