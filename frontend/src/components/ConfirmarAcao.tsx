import { useState, type ReactNode } from 'react'

import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

/**
 * Pede confirmação antes de uma ação difícil de desfazer (revogar, cancelar,
 * mesclar). O gatilho é o próprio botão passado em `children`.
 */
export function ConfirmarAcao({
  titulo,
  descricao,
  rotuloConfirmar,
  destrutiva = true,
  onConfirmar,
  children,
}: {
  titulo: string
  descricao: ReactNode
  rotuloConfirmar: string
  destrutiva?: boolean
  onConfirmar: () => void | Promise<void>
  children: ReactNode
}) {
  const [aberto, setAberto] = useState(false)
  const [ocupado, setOcupado] = useState(false)

  async function confirmar() {
    setOcupado(true)
    try {
      await onConfirmar()
    } finally {
      setOcupado(false)
      setAberto(false)
    }
  }

  return (
    <Dialog open={aberto} onOpenChange={setAberto}>
      <DialogTrigger asChild>{children}</DialogTrigger>
      <DialogContent showCloseButton={false}>
        <DialogHeader>
          <DialogTitle>{titulo}</DialogTitle>
          <DialogDescription>{descricao}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={ocupado}>
              Voltar
            </Button>
          </DialogClose>
          <Button
            type="button"
            variant={destrutiva ? 'destructive' : 'default'}
            onClick={confirmar}
            disabled={ocupado}
          >
            {ocupado ? 'Aguarde…' : rotuloConfirmar}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
