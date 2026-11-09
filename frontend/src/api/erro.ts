/**
 * Extrai a mensagem e o código de um erro da API no formato problem+json
 * (RFC 9457). Centraliza a leitura para as telas exibirem o `detail` do
 * servidor e reagirem a códigos específicos (ex.: `mfa_pendente`).
 */
export interface ErroApi {
  status: number
  code?: string
  detail: string
}

export function lerErro(error: unknown, fallback: string): ErroApi {
  if (error && typeof error === 'object') {
    const problema = error as { title?: string; detail?: string; code?: string; status?: number }
    return {
      status: problema.status ?? 0,
      code: problema.code,
      detail: problema.detail ?? problema.title ?? fallback,
    }
  }
  return { status: 0, detail: fallback }
}