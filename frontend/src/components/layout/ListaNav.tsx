import { cn } from '@/lib/utils'

/** Identificador de tela do portal (espelha o tipo `Tela` de App.tsx). */
export type IdTela =
  | 'inicio'
  | 'perfil'
  | 'dispositivos'
  | 'equipe'
  | 'privacidade'
  | 'participantes'
  | 'dependentes'
  | 'cadastro-assistido'
  | 'vinculos'
  | 'excecao'
  | 'mesclagem'
  | 'auditoria'

interface ItemNav {
  id: IdTela
  rotulo: string
  /** Papéis que enxergam o item. Vazio = todos os usuários autenticados. */
  papeis: string[]
}

/**
 * Itens de navegação e os papéis que os enxergam. A filtragem é só de
 * apresentação: a autoridade real é do servidor (`can:` / matriz de acesso),
 * que recusa o que estiver fora do escopo de qualquer forma.
 */
const ITENS: ItemNav[] = [
  { id: 'inicio', rotulo: 'Início', papeis: [] },
  { id: 'perfil', rotulo: 'Meu perfil', papeis: [] },
  { id: 'dispositivos', rotulo: 'Dispositivos', papeis: [] },
  { id: 'privacidade', rotulo: 'Privacidade', papeis: [] },
  { id: 'dependentes', rotulo: 'Dependentes', papeis: [] },
  { id: 'participantes', rotulo: 'Participantes', papeis: ['coordenadora', 'adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'cadastro-assistido', rotulo: 'Cadastro assistido', papeis: ['coordenadora', 'adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'mesclagem', rotulo: 'Mesclagem', papeis: ['coordenadora', 'adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'equipe', rotulo: 'Equipe', papeis: ['adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'vinculos', rotulo: 'Vínculos', papeis: ['adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'excecao', rotulo: 'Exceção inclusiva', papeis: ['adm_igreja', 'adm_regional', 'super_admin'] },
  { id: 'auditoria', rotulo: 'Auditoria', papeis: ['adm_igreja', 'adm_regional', 'super_admin'] },
]

/** Itens que o conjunto de papéis do usuário pode ver. */
export function itensVisiveis(papeis: string[]): ItemNav[] {
  return ITENS.filter((item) => item.papeis.length === 0 || item.papeis.some((p) => papeis.includes(p)))
}

interface ListaNavProps {
  ativo: IdTela
  papeis: string[]
  onSelecionar: (tela: IdTela) => void
}

/** Lista de navegação reutilizada pela sidebar (desktop) e pela gaveta (celular). */
export function ListaNav({ ativo, papeis, onSelecionar }: ListaNavProps) {
  return (
    <nav className="flex flex-col gap-1" aria-label="Navegação principal">
      {itensVisiveis(papeis).map((item) => {
        const selecionado = item.id === ativo
        return (
          <button
            key={item.id}
            type="button"
            onClick={() => onSelecionar(item.id)}
            aria-current={selecionado ? 'page' : undefined}
            className={cn(
              'rounded-md px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
              selecionado
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
          >
            {item.rotulo}
          </button>
        )
      })}
    </nav>
  )
}