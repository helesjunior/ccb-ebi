import { InboxIcon, Loader2Icon } from 'lucide-react'

import { TableCell, TableRow } from '@/components/ui/table'

/**
 * Linha única de estado para tabelas: "carregando" enquanto a primeira página
 * não chega e "vazio" quando não há linhas. Some quando há dados, sem mudar a
 * estrutura da tabela (cabeçalhos continuam visíveis).
 */
export function EstadoTabela({
  colunas,
  carregando,
  vazio,
  mensagemVazio,
}: {
  colunas: number
  carregando: boolean
  vazio: boolean
  mensagemVazio: string
}) {
  if (!carregando && !vazio) return null
  return (
    <TableRow className="hover:bg-transparent">
      <TableCell colSpan={colunas} className="py-8 text-center text-muted-foreground">
        {carregando ? (
          <span role="status" className="inline-flex items-center gap-2">
            <Loader2Icon className="size-4 animate-spin" aria-hidden="true" />
            Carregando…
          </span>
        ) : (
          <span className="inline-flex flex-col items-center gap-2">
            <InboxIcon className="size-6 opacity-60" aria-hidden="true" />
            {mensagemVazio}
          </span>
        )}
      </TableCell>
    </TableRow>
  )
}
