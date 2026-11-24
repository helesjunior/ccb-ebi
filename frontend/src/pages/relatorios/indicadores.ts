import type { components } from '../../api/schema'

type Indicador = components['schemas']['RelatorioIndicador']
export type RelatorioPresenca = components['schemas']['RelatorioPresenca']
export type NoRelatorio = RelatorioPresenca['no']
export type TotaisRelatorio = RelatorioPresenca['totais']

/** Chaves dos indicadores, como aparecem em `totais` (M-01 a M-06). */
export type ChaveIndicador =
  | 'participantes_distintos'
  | 'presencas'
  | 'sessoes_realizadas'
  | 'media_por_sessao'
  | 'participantes_cadastrados'
  | 'participantes_ativos'
  | 'igrejas_com_ebi_ativo'
  | 'sessoes_previstas'
  | 'sessoes_com_equipe_minima'
  | 'cobertura_escala'

/** Item de exibição: chave do indicador e rótulo humano. */
export interface ItemIndicador {
  chave: ChaveIndicador
  rotulo: string
}

/**
 * Texto exibido para cada indicador, espelhando a decisão 8 da spec:
 * `limiar` vira "< 5", `filho_suprimido` vira "Não divulgado" e
 * `nao_se_aplica` vira "—". A supressão vem decidida do servidor.
 */
export function textoIndicador(indicador?: Indicador | null): string {
  if (!indicador) return '—'
  if (indicador.motivo === 'nao_se_aplica') return '—'
  if (indicador.suprimido) {
    return indicador.motivo === 'limiar' ? '< 5' : 'Não divulgado'
  }
  if (indicador.valor === null || indicador.valor === undefined) return '—'
  return Number.isInteger(indicador.valor)
    ? String(indicador.valor)
    : indicador.valor.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
}

/** Explicação curta do motivo, para o título da célula. */
export function explicacaoIndicador(indicador?: Indicador | null): string | undefined {
  if (indicador?.motivo === 'filho_suprimido') return 'uma unidade abaixo tem menos de 5'
  if (indicador?.motivo === 'limiar') return 'menos de 5 pessoas'
  return undefined
}

/** Rótulo humano do nível do nó. */
const NIVEIS: Record<string, string> = {
  central: 'Central',
  regional: 'Regional',
  setor: 'Setor',
  igreja: 'Igreja',
  ebi: 'EBI',
}

export function rotuloNivel(nivel?: string | null): string {
  return nivel ? (NIVEIS[nivel] ?? nivel) : '—'
}

/** Período padrão da tela: os últimos 30 dias, com `hoje` no formato YYYY-MM-DD. */
export function periodoPadrao(hoje: string): { inicio: string; fim: string } {
  const fim = new Date(`${hoje}T00:00:00`)
  const inicio = new Date(fim)
  inicio.setDate(inicio.getDate() - 29)
  const iso = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  return { inicio: iso(inicio), fim: iso(fim) }
}
