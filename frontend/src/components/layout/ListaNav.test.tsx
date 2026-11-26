import { describe, expect, it } from 'vitest'

import { itensVisiveis } from './ListaNav'

const ids = (papeis: string[]) => itensVisiveis(papeis).map((item) => item.id)

describe('navegacao', () => {
  it('a chefe enxerga tudo o que a coordenadora enxerga', () => {
    const daCoordenadora = new Set(ids(['coordenadora']))
    for (const id of ids(['coordenadora_chefe'])) {
      if (!daCoordenadora.has(id)) {
        // Escalas tambem e da chefe; o resto tem de estar contido.
        expect(['escalas']).toContain(id)
      }
    }
    for (const id of daCoordenadora) {
      expect(ids(['coordenadora_chefe'])).toContain(id)
    }
  })

  it('Escalas aparece para a chefe e nao para o ministerio regional', () => {
    expect(ids(['coordenadora_chefe'])).toContain('escalas')
    expect(ids(['ministerio_regional'])).not.toContain('escalas')
  })

  it('Minhas escalas nao aparece para quem so le a escala', () => {
    expect(ids(['auxiliar'])).toContain('minhas-escalas')
    expect(ids(['ministerio_local'])).not.toContain('minhas-escalas')
  })

  it('Equipes de escala aparece para coordenadora, chefe e adm_igreja', () => {
    for (const papel of ['coordenadora', 'coordenadora_chefe', 'adm_igreja']) {
      expect(ids([papel])).toContain('equipes-escala')
    }
    // O auxiliar se oferece as vagas, mas nao gerencia equipes.
    expect(ids(['auxiliar'])).not.toContain('equipes-escala')
  })
})
