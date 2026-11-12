import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'

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
 * Itens de navegação e os papéis que os enxergam, espelhando a matriz de
 * autorização (`AutorizadorOperacao::MATRIZ` + `docs/05-seguranca-e-privacidade/
 * matriz-de-autorizacao.md`) por operação que cada tela servidor exige. A
 * filtragem é só de apresentação: a autoridade real é do servidor (`can:`), que
 * recusa o que estiver fora do escopo de qualquer forma.
 */
const EQUIPE = ['super_admin', 'adm_regional', 'adm_setor', 'adm_igreja']
const PARTICIPANTES = [
  'super_admin',
  'adm_regional',
  'adm_setor',
  'adm_igreja',
  'coordenadora',
  'auxiliar',
  'ministerio_local',
]
const CADASTRO = ['super_admin', 'adm_igreja', 'coordenadora', 'auxiliar', 'responsavel']
const MESCLAGEM = ['super_admin', 'coordenadora']
const AUDITORIA = ['super_admin', 'adm_regional', 'adm_setor']

const ITENS: ItemNav[] = [
  { id: 'inicio', rotulo: 'Início', papeis: [] },
  { id: 'perfil', rotulo: 'Meu perfil', papeis: [] },
  { id: 'dispositivos', rotulo: 'Dispositivos', papeis: [] },
  { id: 'privacidade', rotulo: 'Privacidade', papeis: [] },
  { id: 'dependentes', rotulo: 'Dependentes', papeis: [] },
  { id: 'participantes', rotulo: 'Participantes', papeis: PARTICIPANTES },
  { id: 'cadastro-assistido', rotulo: 'Cadastro assistido', papeis: CADASTRO },
  { id: 'mesclagem', rotulo: 'Mesclagem', papeis: MESCLAGEM },
  { id: 'equipe', rotulo: 'Equipe', papeis: EQUIPE },
  { id: 'vinculos', rotulo: 'Vínculos', papeis: EQUIPE },
  { id: 'excecao', rotulo: 'Exceção inclusiva', papeis: PARTICIPANTES },
  { id: 'auditoria', rotulo: 'Auditoria', papeis: AUDITORIA },
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
          <Button
            key={item.id}
            type="button"
            variant={selecionado ? 'secondary' : 'ghost'}
            aria-current={selecionado ? 'page' : undefined}
            onClick={() => onSelecionar(item.id)}
            className={cn('justify-start', !selecionado && 'text-muted-foreground')}
          >
            {item.rotulo}
          </Button>
        )
      })}
    </nav>
  )
}