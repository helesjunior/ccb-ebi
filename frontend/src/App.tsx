import { useCallback, useEffect, useState } from 'react'

import { api } from './api/client'
import {
  cabecalhos,
  guardarEscopo,
  guardarToken,
  lerEscopo,
  limparSessao,
  type ContextoAtuacao,
  type Usuario,
} from './api/sessao'
import { AceitarConvite } from './pages/AceitarConvite'
import { Auditoria } from './pages/Auditoria'
import { Dispositivos } from './pages/Dispositivos'
import { Equipe } from './pages/Equipe'
import { Login } from './pages/Login'
import { MeuPerfil } from './pages/MeuPerfil'
import { Privacidade } from './pages/Privacidade'
import { Button } from '@/components/ui/button'

type Tela = 'inicio' | 'perfil' | 'dispositivos' | 'equipe' | 'privacidade' | 'auditoria'

interface ConvitePendente {
  id: string
  token: string
}

/** Lê o convite da URL (`?convite=<id>&token=<secreto>`) para o aceite público. */
function conviteDaUrl(): ConvitePendente | null {
  const params = new URLSearchParams(window.location.search)
  const id = params.get('convite')
  const token = params.get('token')
  return id && token ? { id, token } : null
}

function App() {
  const [convite] = useState<ConvitePendente | null>(conviteDaUrl)
  const [usuario, setUsuario] = useState<Usuario | null>(null)
  const [escopos, setEscopos] = useState<ContextoAtuacao[]>([])
  const [escopo, setEscopo] = useState<ContextoAtuacao | null>(lerEscopo)
  const [tela, setTela] = useState<Tela>('inicio')

  const carregarSessao = useCallback(async () => {
    const [me, scopes] = await Promise.all([
      api.GET('/me', { headers: cabecalhos() }),
      api.GET('/me/scopes', { headers: cabecalhos() }),
    ])
    if (me.error || !me.data) {
      // 403 mfa_pendente: sessão plena ainda não emitida → volta ao login.
      limparSessao()
      setUsuario(null)
      return
    }
    setUsuario(me.data)
    setEscopos(scopes.data?.data ?? [])
  }, [])

  useEffect(() => {
    if (!convite && sessionStorage.getItem('ebi.token')) {
      void carregarSessao()
    }
  }, [convite, carregarSessao])

  function entrar(token: string, dados: Usuario) {
    guardarToken(token)
    setUsuario(dados)
    setEscopo(null)
    guardarEscopo(null)
    void carregarSessao()
  }

  async function sair() {
    await api.POST('/auth/logout', { headers: cabecalhos() })
    limparSessao()
    setUsuario(null)
    setEscopos([])
    setEscopo(null)
  }

  function trocarEscopo(valor: string) {
    const escolhido = escopos.find((e) => chaveEscopo(e) === valor) ?? null
    setEscopo(escolhido)
    guardarEscopo(escolhido)
  }

  if (convite) {
    return <AceitarConvite conviteId={convite.id} token={convite.token} />
  }

  if (!usuario) {
    return <Login onEntrar={entrar} />
  }

  return (
    <>
      <nav>
        <button type="button" onClick={() => setTela('inicio')}>
          Início
        </button>
        <button type="button" onClick={() => setTela('perfil')}>
          Meu perfil
        </button>
        <button type="button" onClick={() => setTela('dispositivos')}>
          Dispositivos
        </button>
        <button type="button" onClick={() => setTela('equipe')}>
          Equipe
        </button>
        <button type="button" onClick={() => setTela('privacidade')}>
          Privacidade
        </button>
        <button type="button" onClick={() => setTela('auditoria')}>
          Auditoria
        </button>
        <Button variant="outline" type="button" onClick={sair}>
          Sair
        </Button>
      </nav>

      <label htmlFor="escopo">Contexto de atuação</label>
      <select id="escopo" value={escopo ? chaveEscopo(escopo) : ''} onChange={(e) => trocarEscopo(e.target.value)}>
        <option value="">Todos os vínculos</option>
        {escopos.map((e) => (
          <option key={chaveEscopo(e)} value={chaveEscopo(e)}>
            {e.papel} — {e.tipo_escopo}:{e.escopo_id}
          </option>
        ))}
      </select>

      {tela === 'inicio' && (
        <main>
          <h1>Bem-vinda</h1>
          <p data-testid="usuario-nome">{usuario.nome}</p>
          <p>{usuario.email}</p>
        </main>
      )}
      {tela === 'perfil' && <MeuPerfil usuario={usuario} />}
      {tela === 'dispositivos' && <Dispositivos />}
      {tela === 'equipe' && <Equipe />}
      {tela === 'privacidade' && <Privacidade />}
      {tela === 'auditoria' && <Auditoria />}
    </>
  )
}

function chaveEscopo(e: ContextoAtuacao): string {
  return `${e.papel}@${e.tipo_escopo}:${e.escopo_id}`
}

export default App