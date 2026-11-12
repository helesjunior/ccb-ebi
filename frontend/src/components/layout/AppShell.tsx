import { useState, type ReactNode } from 'react'

import { TemaToggle } from '@/components/TemaToggle'
import { ListaNav, type IdTela } from '@/components/layout/ListaNav'
import { Button } from '@/components/ui/button'
import type { ContextoAtuacao, Usuario } from '@/api/sessao'

interface AppShellProps {
  titulo: string
  tela: IdTela
  usuario: Usuario
  escopos: ContextoAtuacao[]
  escopo: ContextoAtuacao | null
  onSelecionarTela: (tela: IdTela) => void
  onTrocarEscopo: (valor: string) => void
  onIrPerfil: () => void
  onSair: () => void
  children: ReactNode
}

function chaveEscopo(e: ContextoAtuacao): string {
  return `${e.papel}@${e.tipo_escopo}:${e.escopo_id}`
}

/** Seletor de contexto de atuação (`X-Acting-Scope`), reusado no cabeçalho/gaveta. */
function SeletorEscopo({
  escopos,
  escopo,
  onTrocarEscopo,
  id,
}: {
  escopos: ContextoAtuacao[]
  escopo: ContextoAtuacao | null
  onTrocarEscopo: (valor: string) => void
  id: string
}) {
  return (
    <label className="flex flex-col gap-1 text-xs text-muted-foreground">
      Contexto de atuação
      <select
        id={id}
        value={escopo ? chaveEscopo(escopo) : ''}
        onChange={(e) => onTrocarEscopo(e.target.value)}
        className="h-9 rounded-md border bg-background px-2 text-sm text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
      >
        <option value="">Todos os vínculos</option>
        {escopos.map((e) => (
          <option key={chaveEscopo(e)} value={chaveEscopo(e)}>
            {e.papel} — {e.tipo_escopo}:{e.escopo_id}
          </option>
        ))}
      </select>
    </label>
  )
}

/** Menu do usuário: nome, "Editar perfil" e "Sair". */
function MenuUsuario({
  usuario,
  onIrPerfil,
  onSair,
}: {
  usuario: Usuario
  onIrPerfil: () => void
  onSair: () => void
}) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div className="min-w-0">
        <p className="truncate text-sm font-medium">{usuario.nome}</p>
        <p className="truncate text-xs text-muted-foreground">{usuario.email}</p>
      </div>
      <div className="flex gap-2">
        <Button variant="outline" size="sm" type="button" onClick={onIrPerfil}>
          Editar perfil
        </Button>
        <Button variant="ghost" size="sm" type="button" onClick={onSair}>
          Sair
        </Button>
      </div>
    </div>
  )
}

/**
 * Casca de aplicação: sidebar no desktop, gaveta no celular, cabeçalho com
 * título, tema, contexto de atuação e menu do usuário. Todas as telas
 * autenticadas são renderizadas dentro dela.
 */
export function AppShell({
  titulo,
  tela,
  usuario,
  escopos,
  escopo,
  onSelecionarTela,
  onTrocarEscopo,
  onIrPerfil,
  onSair,
  children,
}: AppShellProps) {
  const [gavetaAberta, setGavetaAberta] = useState(false)

  function selecionar(id: IdTela) {
    onSelecionarTela(id)
    setGavetaAberta(false)
  }

  return (
    <div className="min-h-svh bg-background text-foreground">
      <div className="mx-auto flex w-full max-w-[1400px]">
        {/* Sidebar (desktop) */}
        <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col gap-6 border-r p-4 md:flex">
          <p className="px-3 text-lg font-semibold">EBI</p>
          <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
          <div className="mt-auto flex flex-col gap-3">
            <SeletorEscopo
              id="escopo-sidebar"
              escopos={escopos}
              escopo={escopo}
              onTrocarEscopo={onTrocarEscopo}
            />
            <MenuUsuario usuario={usuario} onIrPerfil={onIrPerfil} onSair={onSair} />
          </div>
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          {/* Cabeçalho (mobile: gaveta) / topo (desktop) */}
          <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:px-8">
            <div className="flex min-w-0 items-center gap-3">
              <button
                type="button"
                onClick={() => setGavetaAberta((v) => !v)}
                aria-expanded={gavetaAberta}
                aria-controls="gaveta-nav"
                aria-label="Abrir menu de navegação"
                className="rounded-md border px-3 py-2 text-sm md:hidden"
              >
                ☰ Menu
              </button>
              <h1 className="truncate text-xl font-semibold">{titulo}</h1>
            </div>
            <TemaToggle />
          </header>

          {/* Gaveta (mobile) */}
          {gavetaAberta && (
            <div id="gaveta-nav" className="flex flex-col gap-4 border-b p-4 md:hidden">
              <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
              <SeletorEscopo
                id="escopo-gaveta"
                escopos={escopos}
                escopo={escopo}
                onTrocarEscopo={onTrocarEscopo}
              />
              <MenuUsuario usuario={usuario} onIrPerfil={onIrPerfil} onSair={onSair} />
            </div>
          )}

          <main className="flex-1 p-4 md:p-8">{children}</main>
        </div>
      </div>
    </div>
  )
}