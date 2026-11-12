export type Tema = 'light' | 'dark' | 'system'

const CHAVE = 'ebi.tema'

export function lerTema(): Tema {
  const bruto = localStorage.getItem(CHAVE)
  return bruto === 'light' || bruto === 'dark' || bruto === 'system' ? bruto : 'system'
}

export function aplicarTema(tema: Tema): void {
  const escuro =
    tema === 'dark' ||
    (tema === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.classList.toggle('dark', escuro)
}

/**
 * Passa a reagir à mudança do tema do sistema quando a preferência é
 * `system` (o padrão). Devolve a função de limpeza; o callback deixa o
 * consumidor re-renderizar (o toaster lê `.dark` no render).
 */
export function observarTemaSistema(aoMudar: () => void): () => void {
  const consulta = window.matchMedia('(prefers-color-scheme: dark)')
  const ouvinte = () => {
    if (lerTema() === 'system') {
      aplicarTema('system')
      aoMudar()
    }
  }
  consulta.addEventListener('change', ouvinte)
  return () => consulta.removeEventListener('change', ouvinte)
}

export function alternarTema(): Tema {
  const atual = lerTema()
  const proximo: Tema =
    atual === 'dark'
      ? 'light'
      : atual === 'light'
        ? 'system'
        : window.matchMedia('(prefers-color-scheme: dark)').matches
          ? 'light'
          : 'dark'
  localStorage.setItem(CHAVE, proximo)
  aplicarTema(proximo)
  return proximo
}

/** Rótulo humano do tema, para o botão de alternância. */
export function rotuloTema(tema: Tema): string {
  return tema === 'dark' ? 'Escuro' : tema === 'light' ? 'Claro' : 'Automático'
}