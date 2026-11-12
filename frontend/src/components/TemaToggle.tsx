import { useState } from 'react'

import { alternarTema, lerTema, rotuloTema, type Tema } from '@/lib/tema'

/**
 * Alterna entre tema claro, escuro e automático (sistema). A preferência é
 * persistida em `localStorage` e aplicada pela classe `.dark` no `<html>`
 * (ver `lib/tema.ts` e o script anti-flash em `index.html`).
 */
export function TemaToggle() {
  const [tema, setTema] = useState<Tema>(lerTema)

  function alternar() {
    setTema(alternarTema())
  }

  return (
    <button
      type="button"
      onClick={alternar}
      aria-label={`Tema: ${rotuloTema(tema)}. Alternar tema`}
      title={`Tema: ${rotuloTema(tema)}`}
      className="inline-flex h-9 items-center gap-2 rounded-md border bg-background px-3 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <span aria-hidden="true">{tema === 'dark' ? '🌙' : tema === 'light' ? '☀️' : '🖥️'}</span>
      <span>{rotuloTema(tema)}</span>
    </button>
  )
}