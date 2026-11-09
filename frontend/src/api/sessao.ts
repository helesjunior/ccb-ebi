import type { components } from './schema'

/**
 * Estado de sessão do portal, guardado em sessionStorage (escopo de aba).
 * O token de desafio (MFA) fica separado do token de sessão plena para deixar
 * claro que ele não alcança a área operacional (CA-15).
 */
export type Usuario = components['schemas']['Usuario']
export type ContextoAtuacao = components['schemas']['ContextoAtuacao']

const CHAVE_TOKEN = 'ebi.token'
const CHAVE_ESCOPO = 'ebi.escopo'

export function guardarToken(token: string): void {
  sessionStorage.setItem(CHAVE_TOKEN, token)
}

export function lerToken(): string | null {
  return sessionStorage.getItem(CHAVE_TOKEN)
}

export function limparSessao(): void {
  sessionStorage.removeItem(CHAVE_TOKEN)
  sessionStorage.removeItem(CHAVE_ESCOPO)
}

export function guardarEscopo(escopo: ContextoAtuacao | null): void {
  if (escopo) {
    sessionStorage.setItem(CHAVE_ESCOPO, JSON.stringify(escopo))
  } else {
    sessionStorage.removeItem(CHAVE_ESCOPO)
  }
}

export function lerEscopo(): ContextoAtuacao | null {
  const bruto = sessionStorage.getItem(CHAVE_ESCOPO)
  return bruto ? (JSON.parse(bruto) as ContextoAtuacao) : null
}

/**
 * Cabeçalhos de autenticação. Quando há contexto de atuação selecionado, o
 * servidor recebe `X-Acting-Scope` no formato `papel=<codigo>;escopo=<tipo>:<id>`
 * para revalidar o escopo (RN-11). O token vem do parâmetro (para o desafio de
 * MFA) ou da sessão.
 */
export function cabecalhos(token?: string, escopo?: ContextoAtuacao | null): Record<string, string> {
  const cabecalhos: Record<string, string> = {}
  const efetivo = token ?? lerToken()
  if (efetivo) {
    cabecalhos.Authorization = `Bearer ${efetivo}`
  }
  const contexto = escopo ?? lerEscopo()
  if (contexto?.papel && contexto.tipo_escopo && contexto.escopo_id) {
    cabecalhos['X-Acting-Scope'] = `papel=${contexto.papel};escopo=${contexto.tipo_escopo}:${contexto.escopo_id}`
  }
  return cabecalhos
}