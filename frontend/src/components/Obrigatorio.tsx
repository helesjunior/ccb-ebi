/** Marca visual de campo obrigatório; leitores de tela já recebem `required`. */
export function Obrigatorio() {
  return (
    <span aria-hidden="true" className="text-destructive">
      *
    </span>
  )
}
