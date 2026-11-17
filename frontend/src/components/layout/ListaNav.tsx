import {
  AccessibilityIcon,
  CalendarDaysIcon,
  CombineIcon,
  HouseIcon,
  LinkIcon,
  MonitorSmartphoneIcon,
  ScrollTextIcon,
  SearchIcon,
  ShieldCheckIcon,
  UserIcon,
  UserPlusIcon,
  UsersIcon,
  UsersRoundIcon,
  type LucideIcon,
} from 'lucide-react'

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
  | 'sessoes'
  | 'presenca'
  | 'cadastro-assistido'
  | 'vinculos'
  | 'excecao'
  | 'mesclagem'
  | 'auditoria'

/** Grupos da navegação, na ordem em que aparecem. */
export type GrupoNav = 'Geral' | 'Participantes' | 'Administração' | 'Minha conta'

const GRUPOS: GrupoNav[] = ['Geral', 'Participantes', 'Administração', 'Minha conta']

export interface ItemNav {
  id: IdTela
  rotulo: string
  /** Frase curta do que a tela faz (atalhos da tela inicial). */
  descricao: string
  icone: LucideIcon
  grupo: GrupoNav
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
const SESSOES = [
  'super_admin',
  'adm_regional',
  'adm_setor',
  'adm_igreja',
  'coordenadora',
  'auxiliar',
  'ministerio_local',
]
const MESCLAGEM = ['super_admin', 'coordenadora']
const AUDITORIA = ['super_admin', 'adm_regional', 'adm_setor']

const ITENS: ItemNav[] = [
  { id: 'inicio', rotulo: 'Início', descricao: 'Visão geral e atalhos.', icone: HouseIcon, grupo: 'Geral', papeis: [] },
  {
    id: 'dependentes',
    rotulo: 'Meus dependentes',
    descricao: 'Crianças e adolescentes que você representa.',
    icone: UsersRoundIcon,
    grupo: 'Participantes',
    papeis: [],
  },
  {
    id: 'sessoes',
    rotulo: 'Sessões',
    descricao: 'Abra, encerre e registre presença.',
    icone: CalendarDaysIcon,
    grupo: 'Participantes',
    papeis: SESSOES,
  },
  {
    id: 'participantes',
    rotulo: 'Participantes',
    descricao: 'Cadastre e busque participantes.',
    icone: SearchIcon,
    grupo: 'Participantes',
    papeis: PARTICIPANTES,
  },
  {
    id: 'cadastro-assistido',
    rotulo: 'Cadastro assistido',
    descricao: 'Cadastro presencial com evidência de aceite.',
    icone: UserPlusIcon,
    grupo: 'Participantes',
    papeis: CADASTRO,
  },
  {
    id: 'excecao',
    rotulo: 'Exceção inclusiva',
    descricao: 'Peça ou decida exceções de faixa etária.',
    icone: AccessibilityIcon,
    grupo: 'Participantes',
    papeis: PARTICIPANTES,
  },
  {
    id: 'mesclagem',
    rotulo: 'Mesclagem',
    descricao: 'Junte cadastros duplicados (reversível).',
    icone: CombineIcon,
    grupo: 'Participantes',
    papeis: MESCLAGEM,
  },
  {
    id: 'equipe',
    rotulo: 'Equipe',
    descricao: 'Usuários do escopo e convites.',
    icone: UsersIcon,
    grupo: 'Administração',
    papeis: EQUIPE,
  },
  {
    id: 'vinculos',
    rotulo: 'Vínculos',
    descricao: 'Pedidos de vínculo entre responsável e participante.',
    icone: LinkIcon,
    grupo: 'Administração',
    papeis: EQUIPE,
  },
  {
    id: 'auditoria',
    rotulo: 'Auditoria',
    descricao: 'Trilha de eventos do sistema.',
    icone: ScrollTextIcon,
    grupo: 'Administração',
    papeis: AUDITORIA,
  },
  {
    id: 'perfil',
    rotulo: 'Meu perfil',
    descricao: 'Nome, e-mail, senha e verificação em duas etapas.',
    icone: UserIcon,
    grupo: 'Minha conta',
    papeis: [],
  },
  {
    id: 'dispositivos',
    rotulo: 'Dispositivos',
    descricao: 'Onde sua conta está conectada.',
    icone: MonitorSmartphoneIcon,
    grupo: 'Minha conta',
    papeis: [],
  },
  {
    id: 'privacidade',
    rotulo: 'Privacidade',
    descricao: 'Termo, consentimento e pedidos sobre seus dados.',
    icone: ShieldCheckIcon,
    grupo: 'Minha conta',
    papeis: [],
  },
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
  const visiveis = itensVisiveis(papeis)
  return (
    <nav className="flex flex-col gap-4" aria-label="Navegação principal">
      {GRUPOS.map((grupo) => {
        const itens = visiveis.filter((item) => item.grupo === grupo)
        if (itens.length === 0) return null
        const idGrupo = `nav-grupo-${grupo.replace(/\s+/g, '-').toLowerCase()}`
        return (
          <div key={grupo} className="flex flex-col gap-1">
            {grupo !== 'Geral' && (
              <p
                id={idGrupo}
                className="px-3 pb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
              >
                {grupo}
              </p>
            )}
            <ul className="flex flex-col gap-1" aria-labelledby={grupo !== 'Geral' ? idGrupo : undefined}>
              {itens.map((item) => {
                const selecionado = item.id === ativo
                const Icone = item.icone
                return (
                  <li key={item.id}>
                    <Button
                      type="button"
                      variant={selecionado ? 'secondary' : 'ghost'}
                      aria-current={selecionado ? 'page' : undefined}
                      onClick={() => onSelecionar(item.id)}
                      className={cn(
                        'w-full justify-start gap-3',
                        selecionado
                          ? 'bg-primary/10 font-semibold text-primary hover:bg-primary/15'
                          : 'text-muted-foreground hover:text-foreground',
                      )}
                    >
                      <Icone className="size-4 shrink-0" aria-hidden="true" />
                      <span className="truncate">{item.rotulo}</span>
                    </Button>
                  </li>
                )
              })}
            </ul>
          </div>
        )
      })}
    </nav>
  )
}
