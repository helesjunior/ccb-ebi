import { useEffect, useState } from 'react'
import { MoonIcon, SunIcon, MonitorIcon } from 'lucide-react'

import { Button } from '@/components/ui/button'
import { alternarTema, lerTema, observarTemaSistema, rotuloTema, type Tema } from '@/lib/tema'

const ICONES: Record<Tema, typeof SunIcon> = {
  light: SunIcon,
  dark: MoonIcon,
  system: MonitorIcon,
}

/**
 * Alterna entre tema claro, escuro e automático (sistema). A preferência é
 * persistida em `localStorage` e aplicada pela classe `.dark` no `<html>`
 * (ver `lib/tema.ts` e o script anti-flash em `index.html`).
 */
export function TemaToggle() {
  const [tema, setTema] = useState<Tema>(lerTema)
  const Icone = ICONES[tema]

  // No modo automático, acompanha a mudança de tema do sistema operacional.
  // O re-render também atualiza componentes que leem `.dark` (ex.: Toaster).
  useEffect(() => observarTemaSistema(() => setTema((atual) => atual)), [])

  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      onClick={() => setTema(alternarTema())}
      aria-label={`Tema: ${rotuloTema(tema)}. Alternar tema`}
      title={`Tema: ${rotuloTema(tema)}`}
    >
      <Icone className="size-4" aria-hidden="true" />
      <span className="hidden sm:inline">{rotuloTema(tema)}</span>
    </Button>
  )
}