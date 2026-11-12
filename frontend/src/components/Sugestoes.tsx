/** `<datalist>` com sugestões para um campo de texto livre. */
export function Sugestoes({
  id,
  opcoes,
}: {
  id: string
  opcoes: { valor: string; rotulo: string }[]
}) {
  return (
    <datalist id={id}>
      {opcoes.map((o) => (
        <option key={o.valor} value={o.valor} label={o.rotulo} />
      ))}
    </datalist>
  )
}
