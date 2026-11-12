/**
 * Formatação para exibição: datas no padrão brasileiro e rótulos humanos para
 * códigos da API (papéis, escopos, status). Só apresentação: o valor enviado
 * ao servidor continua sendo o código original.
 */

/** `2018-03-05` → `05/03/2018`. Aceita também data-hora ISO. Vazio → `—`. */
export function formatarData(valor?: string | null): string {
  if (!valor) return '—'
  const soData = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor)
  if (soData) return `${soData[3]}/${soData[2]}/${soData[1]}`
  const data = new Date(valor)
  return Number.isNaN(data.getTime()) ? valor : data.toLocaleDateString('pt-BR')
}

/** Data-hora ISO → `05/03/2018 14:30`. */
export function formatarDataHora(valor?: string | null): string {
  if (!valor) return '—'
  const data = new Date(valor)
  return Number.isNaN(data.getTime())
    ? valor
    : data.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })
}

const PAPEIS: Record<string, string> = {
  super_admin: 'Administração geral',
  adm_regional: 'Administração regional',
  adm_setor: 'Administração de setor',
  adm_igreja: 'Administração da igreja',
  coordenadora: 'Coordenação',
  auxiliar: 'Auxiliar',
  ministerio_local: 'Ministério local',
  responsavel: 'Responsável',
}

/** Papéis conhecidos, na ordem em que aparecem nos seletores. */
export const PAPEIS_CONHECIDOS = Object.keys(PAPEIS)

export function rotuloPapel(codigo?: string | null): string {
  if (!codigo) return '—'
  return PAPEIS[codigo] ?? codigo
}

const TIPOS_ESCOPO: Record<string, string> = {
  central: 'Central',
  regional: 'Regional',
  setor: 'Setor',
  igreja: 'Igreja',
  ebi: 'EBI',
}

export function rotuloTipoEscopo(codigo?: string | null): string {
  if (!codigo) return '—'
  return TIPOS_ESCOPO[codigo] ?? codigo
}

const STATUS: Record<string, string> = {
  ativo: 'Ativo',
  inativo: 'Inativo',
  suspenso: 'Suspenso',
  suspensa: 'Suspensa',
  pendente: 'Pendente',
  aprovada: 'Aprovada',
  aprovado: 'Aprovado',
  recusada: 'Recusada',
  recusado: 'Recusado',
  revogada: 'Revogada',
  expirada: 'Expirada',
  contestada: 'Contestada',
  aguardando_documento: 'Aguardando documento',
  solicitada: 'Solicitada',
  aceito: 'Aceito',
  retirado: 'Retirado',
  mesclado: 'Mesclado',
}

/** Rótulo de status; códigos desconhecidos aparecem com o `_` trocado por espaço. */
export function rotuloStatus(codigo?: string | null): string {
  if (!codigo) return '—'
  return STATUS[codigo] ?? codigo.replaceAll('_', ' ')
}

/** Primeiro nome, para saudações. */
export function primeiroNome(nome?: string | null): string {
  return (nome ?? '').trim().split(/\s+/)[0] ?? ''
}

/** Sugestões para o campo livre "tipo de relação" (o servidor aceita qualquer texto). */
export const SUGESTOES_RELACAO: { valor: string; rotulo: string }[] = [
  { valor: 'mae', rotulo: 'Mãe' },
  { valor: 'pai', rotulo: 'Pai' },
  { valor: 'avo', rotulo: 'Avó / avô' },
  { valor: 'tio', rotulo: 'Tia / tio' },
  { valor: 'irmao', rotulo: 'Irmã / irmão' },
  { valor: 'tutor', rotulo: 'Tutor(a) legal' },
  { valor: 'responsavel', rotulo: 'Outro responsável' },
]

/** Sugestões para a finalidade declarada da leitura identificável (texto livre, auditado). */
export const SUGESTOES_FINALIDADE: { valor: string; rotulo: string }[] = [
  { valor: 'confirmacao_cadastro', rotulo: 'Confirmar se já existe cadastro' },
  { valor: 'deduplicacao', rotulo: 'Encontrar cadastros duplicados' },
  { valor: 'atendimento_responsavel', rotulo: 'Atender um responsável' },
]

/** Data de hoje no formato do `<input type="date">` (para `max`). */
export function hojeIso(): string {
  const d = new Date()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${mm}-${dd}`
}
