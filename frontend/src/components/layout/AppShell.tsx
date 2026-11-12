import { useEffect, useRef, useState, type ReactNode } from 'react'
import { BookOpenIcon, ChevronsUpDownIcon, LogOutIcon, MenuIcon, UserIcon } from 'lucide-react'

import { TemaToggle } from '@/components/TemaToggle'
import { ListaNav, type IdTela } from '@/components/layout/ListaNav'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet'
import { Toaster } from '@/components/ui/sonner'
import { TooltipProvider } from '@/components/ui/tooltip'
import type { ContextoAtuacao, Usuario } from '@/api/sessao'
import { rotuloPapel, rotuloTipoEscopo } from '@/lib/formatos'

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

/** "Coordenação · Igreja" — o ID completo fica no `title` para quem precisar. */
function rotuloEscopo(e: ContextoAtuacao): string {
  return `${rotuloPapel(e.papel)} · ${rotuloTipoEscopo(e.tipo_escopo)}`
}

/** Marca do portal, usada na sidebar e na gaveta. */
function Marca() {
  return (
    <div className="flex items-center gap-3 px-3">
      <span className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
        <BookOpenIcon className="size-5" aria-hidden="true" />
      </span>
      <span className="flex flex-col leading-tight">
        <span className="text-base font-semibold">EBI</span>
        <span className="text-xs text-muted-foreground">Portal de gestão</span>
      </span>
    </div>
  )
}

/** Seletor de contexto de atuação (`X-Acting-Scope`), reusado no cabeçalho/gaveta. */
function SeletorEscopo({
  escopos,
  escopo,
  onTrocarEscopo,
}: {
  escopos: ContextoAtuacao[]
  escopo: ContextoAtuacao | null
  onTrocarEscopo: (valor: string) => void
}) {
  return (
    <div className="flex flex-col gap-1">
      <span className="px-1 text-xs font-medium text-muted-foreground">Atuando como</span>
      <Select
        value={escopo ? chaveEscopo(escopo) : 'todos'}
        onValueChange={(valor) => onTrocarEscopo(valor === 'todos' ? '' : valor)}
      >
        <SelectTrigger aria-label="Contexto de atuação" className="w-full">
          <SelectValue placeholder="Todos os vínculos" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="todos">Todos os vínculos</SelectItem>
          {escopos.map((e) => (
            <SelectItem
              key={chaveEscopo(e)}
              value={chaveEscopo(e)}
              title={`${e.papel} — ${e.tipo_escopo}:${e.escopo_id}`}
            >
              {rotuloEscopo(e)}
              <span className="ml-1 text-xs text-muted-foreground">
                ({e.escopo_id?.slice(-6)})
              </span>
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}

function iniciais(nome?: string | null): string {
  const partes = (nome ?? '').trim().split(/\s+/).filter(Boolean)
  const primeira = partes[0]?.[0] ?? ''
  const ultima = partes.length > 1 ? (partes[partes.length - 1]?.[0] ?? '') : ''
  return (primeira + ultima).toUpperCase() || '?'
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
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-auto w-full justify-start gap-3 px-2 py-2">
          <span
            aria-hidden="true"
            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-semibold text-primary"
          >
            {iniciais(usuario.nome)}
          </span>
          <span className="flex min-w-0 flex-1 flex-col text-left leading-tight">
            <span className="truncate text-sm font-medium">{usuario.nome}</span>
            <span className="truncate text-xs font-normal text-muted-foreground">
              {usuario.email}
            </span>
          </span>
          <ChevronsUpDownIcon className="size-4 shrink-0 opacity-60" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-56">
        <DropdownMenuLabel className="flex flex-col">
          <span className="truncate font-medium">{usuario.nome}</span>
          <span className="truncate text-xs font-normal text-muted-foreground">
            {usuario.email}
          </span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={onIrPerfil}>
          <UserIcon className="size-4" aria-hidden="true" />
          Editar perfil
        </DropdownMenuItem>
        <DropdownMenuItem variant="destructive" onSelect={onSair}>
          <LogOutIcon className="size-4" aria-hidden="true" />
          Sair
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

/**
 * Casca de aplicação: sidebar no desktop, gaveta (Sheet) no celular,
 * cabeçalho com título, tema, contexto de atuação e menu do usuário.
 * Todas as telas autenticadas são renderizadas dentro dela.
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
  const tituloRef = useRef<HTMLHeadingElement>(null)
  const primeiraTela = useRef(true)

  // Ao trocar de tela: volta ao topo e leva o foco ao título, para quem usa
  // teclado ou leitor de tela saber que o conteúdo mudou (RNF-05).
  useEffect(() => {
    if (primeiraTela.current) {
      primeiraTela.current = false
      return
    }
    window.scrollTo({ top: 0 })
    tituloRef.current?.focus()
  }, [tela])

  useEffect(() => {
    document.title = `${titulo} · EBI`
  }, [titulo])

  function selecionar(id: IdTela) {
    onSelecionarTela(id)
    setGavetaAberta(false)
  }

  return (
    <TooltipProvider>
      <div className="min-h-svh bg-background text-foreground">
        <a
          href="#conteudo"
          className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground"
        >
          Pular para o conteúdo
        </a>
        <div className="mx-auto flex w-full max-w-[1400px]">
          {/* Sidebar (desktop) */}
          <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col gap-6 border-r bg-muted/40 p-4 md:flex">
            <Marca />
            <div className="flex-1 overflow-y-auto">
              <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
            </div>
            <div className="flex flex-col gap-3 border-t pt-4">
              {escopos.length > 0 && (
                <SeletorEscopo escopos={escopos} escopo={escopo} onTrocarEscopo={onTrocarEscopo} />
              )}
              <MenuUsuario usuario={usuario} onIrPerfil={onIrPerfil} onSair={onSair} />
            </div>
          </aside>

          <div className="flex min-w-0 flex-1 flex-col">
            {/* Cabeçalho (mobile: gaveta) / topo (desktop) */}
            <header className="sticky top-0 z-10 flex items-center justify-between gap-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:px-8">
              <div className="flex min-w-0 items-center gap-3">
                {/* Gaveta (mobile) */}
                <Sheet open={gavetaAberta} onOpenChange={setGavetaAberta}>
                  <SheetTrigger asChild>
                    <Button
                      variant="outline"
                      size="sm"
                      className="md:hidden"
                      aria-label="Abrir menu de navegação"
                    >
                      <MenuIcon className="size-4" aria-hidden="true" />
                      <span className="sr-only sm:not-sr-only">Menu</span>
                    </Button>
                  </SheetTrigger>
                  <SheetContent side="left" className="flex flex-col gap-6 overflow-y-auto p-4">
                    <SheetHeader className="p-0">
                      <SheetTitle className="sr-only">Menu</SheetTitle>
                      <Marca />
                    </SheetHeader>
                    <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
                    <div className="mt-auto flex flex-col gap-3 border-t pt-4">
                      {escopos.length > 0 && (
                        <SeletorEscopo
                          escopos={escopos}
                          escopo={escopo}
                          onTrocarEscopo={onTrocarEscopo}
                        />
                      )}
                      <MenuUsuario usuario={usuario} onIrPerfil={onIrPerfil} onSair={onSair} />
                    </div>
                  </SheetContent>
                </Sheet>
                <h1
                  ref={tituloRef}
                  tabIndex={-1}
                  className="truncate text-lg font-semibold outline-none md:text-xl"
                >
                  {titulo}
                </h1>
              </div>
              <TemaToggle />
            </header>

            <main id="conteudo" className="flex-1 p-4 pb-12 md:p-8">
              {children}
            </main>
          </div>
        </div>
        <Toaster richColors closeButton />
      </div>
    </TooltipProvider>
  )
}