"use client"

import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
} from "lucide-react"
import { useEffect, useState } from "react"
import { Toaster as Sonner, type ToasterProps } from "sonner"

import { observarTemaSistema } from "@/lib/tema"

/**
 * Toaster (sonner) com o tema sincronizado à classe `.dark` do app.
 * Não usa `next-themes`: o tema do EBI vive em `lib/tema.ts`.
 */
const Toaster = ({ ...props }: ToasterProps) => {
  // O tema vive em `.dark` no `<html>`; o estado local só re-renderiza quando o
  // sistema muda de tema no modo automático.
  const [, setVersao] = useState(0)
  useEffect(() => observarTemaSistema(() => setVersao((v) => v + 1)), [])

  const escuro =
    typeof document !== 'undefined' && document.documentElement.classList.contains('dark')

  return (
    <Sonner
      theme={escuro ? 'dark' : 'light'}
      className="toaster group"
      icons={{
        success: <CircleCheckIcon className="size-4" />,
        info: <InfoIcon className="size-4" />,
        warning: <TriangleAlertIcon className="size-4" />,
        error: <OctagonXIcon className="size-4" />,
        loading: <Loader2Icon className="size-4 animate-spin" />,
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties
      }
      {...props}
    />
  )
}

export { Toaster }
