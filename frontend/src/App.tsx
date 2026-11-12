import { useCallback, useEffect, useState } from 'react'

import { api } from './api/client'
import { AppShell } from './components/layout/AppShell'
import type { IdTela } from './components/layout/ListaNav'
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
import { CadastroAssistido } from './pages/CadastroAssistido'
import { Dependentes } from './pages/Dependentes'
import { Dispositivos } from './pages/Dispositivos'
import { Equipe } from './pages/Equipe'
import { ExcecaoInclusiva } from './pages/ExcecaoInclusiva'
import { Login } from './pages/Login'
import { Mesclagem } from './pages/Mesclagem'
import { MeuPerfil } from './pages/MeuPerfil'
import { Participantes } from './pages/Participantes'
import { Privacidade } from './pages/Privacidade'
import { Vinculos } from './pages/Vinculos'

type Tela = IdTela

/** Título humano de cada tela, exibido no cabeçalho da casca. */
const ROTULOS: Record<Tela, string> = {
  inicio: 'Início',
  perfil: 'Meu perfil',
  dispositivos: 'Dispositivos',
  equipe: 'Equipe',
  privacidade: 'Privacidade',
  auditoria: 'Auditoria',
  participantes: 'Participantes',
  dependentes: 'Dependentes',
  'cadastro-assistido': 'Cadastro assistido',
  vinculos: 'Vínculos',
  excecao: 'Exceção inclusiva',
  mesclagem: 'Mesclagem',
}

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
    <AppShell
      titulo={ROTULOS[tela]}
      tela={tela}
      usuario={usuario}
      escopos={escopos}
      escopo={escopo}
      onSelecionarTela={setTela}
      onTrocarEscopo={trocarEscopo}
      onIrPerfil={() => setTela('perfil')}
      onSair={sair}
    >
      {tela === 'inicio' && (
        <section className="mx-auto max-w-2xl">
          <h2 className="text-2xl font-semibold">Bem-vinda</h2>
          <p className="mt-2 text-muted-foreground" data-testid="usuario-nome">
            {usuario.nome}
          </p>
          <p className="text-muted-foreground">{usuario.email}</p>
          <p className="mt-4 text-sm text-muted-foreground">
            Use o menu para navegar. O contexto de atuação e o tema ficam no cabeçalho.
          </p>
        </section>
      )}
      {tela === 'perfil' && <MeuPerfil usuario={usuario} onAtualizado={setUsuario} />}
      {tela === 'dispositivos' && <Dispositivos />}
      {tela === 'equipe' && <Equipe />}
      {tela === 'privacidade' && <Privacidade />}
      {tela === 'participantes' && <Participantes />}
      {tela === 'dependentes' && <Dependentes />}
      {tela === 'cadastro-assistido' && <CadastroAssistido />}
      {tela === 'vinculos' && <Vinculos />}
      {tela === 'excecao' && <ExcecaoInclusiva />}
      {tela === 'mesclagem' && <Mesclagem />}
      {tela === 'auditoria' && <Auditoria />}
    </AppShell>
  )
}

function chaveEscopo(e: ContextoAtuacao): string {
  return `${e.papel}@${e.tipo_escopo}:${e.escopo_id}`
}

export default App