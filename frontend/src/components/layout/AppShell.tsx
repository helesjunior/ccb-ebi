import { useState, type ReactNode } from 'react'
import { LogOutIcon, MenuIcon, UserIcon } from 'lucide-react'

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
}: {
  escopos: ContextoAtuacao[]
  escopo: ContextoAtuacao | null
  onTrocarEscopo: (valor: string) => void
}) {
  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs text-muted-foreground">Contexto de atuação</span>
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
            <SelectItem key={chaveEscopo(e)} value={chaveEscopo(e)}>
              {e.papel} — {e.tipo_escopo}:{e.escopo_id}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
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
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="w-full justify-start gap-2">
          <UserIcon className="size-4 shrink-0" aria-hidden="true" />
          <span className="truncate">{usuario.nome}</span>
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

  function selecionar(id: IdTela) {
    onSelecionarTela(id)
    setGavetaAberta(false)
  }

  return (
    <TooltipProvider>
      <div className="min-h-svh bg-background text-foreground">
        <div className="mx-auto flex w-full max-w-[1400px]">
          {/* Sidebar (desktop) */}
          <aside className="sticky top-0 hidden h-svh w-64 shrink-0 flex-col gap-6 border-r p-4 md:flex">
            <p className="px-3 text-lg font-semibold">EBI</p>
            <div className="flex-1 overflow-y-auto">
              <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
            </div>
            <div className="flex flex-col gap-3">
              <SeletorEscopo escopos={escopos} escopo={escopo} onTrocarEscopo={onTrocarEscopo} />
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
                      Menu
                    </Button>
                  </SheetTrigger>
                  <SheetContent side="left" className="flex flex-col gap-6 overflow-y-auto p-4">
                    <SheetHeader className="p-0">
                      <SheetTitle>EBI</SheetTitle>
                    </SheetHeader>
                    <ListaNav ativo={tela} papeis={usuario.papeis ?? []} onSelecionar={selecionar} />
                    <div className="mt-auto flex flex-col gap-3">
                      <SeletorEscopo
                        escopos={escopos}
                        escopo={escopo}
                        onTrocarEscopo={onTrocarEscopo}
                      />
                      <MenuUsuario usuario={usuario} onIrPerfil={onIrPerfil} onSair={onSair} />
                    </div>
                  </SheetContent>
                </Sheet>
                <h1 className="truncate text-xl font-semibold">{titulo}</h1>
              </div>
              <TemaToggle />
            </header>

            <main className="flex-1 p-4 md:p-8">{children}</main>
          </div>
        </div>
        <Toaster />
      </div>
    </TooltipProvider>
  )
}