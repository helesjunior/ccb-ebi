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