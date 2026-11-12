import type { Usuario } from '../api/sessao'
import { Badge } from '@/components/ui/badge'
import { DadosForm } from './perfil/DadosForm'
import { SenhaForm } from './perfil/SenhaForm'
import { SegurancaMfa } from './perfil/SegurancaMfa'

interface MeuPerfilProps {
  usuario: Usuario
  onAtualizado: (usuario: Usuario) => void
}

/**
 * Meu perfil: dados pessoais, troca de senha e segurança (MFA). O cabeçalho
 * resume a identidade; os três cartões cuidam da auto-gestão do usuário.
 */
export function MeuPerfil({ usuario, onAtualizado }: MeuPerfilProps) {
  const papeis = usuario.papeis ?? []

  return (
    <section className="mx-auto flex max-w-4xl flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h2 className="text-2xl font-semibold" data-testid="perfil-nome">
          {usuario.nome}
        </h2>
        <p className="text-muted-foreground">{usuario.email}</p>
        <div className="flex flex-wrap gap-2 pt-1">
          {papeis.length > 0 ? (
            papeis.map((p) => (
              <Badge key={p} variant="secondary">
                {p}
              </Badge>
            ))
          ) : (
            <Badge variant="outline">sem papéis</Badge>
          )}
        </div>
      </header>

      <div className="grid gap-6 md:grid-cols-2">
        <DadosForm usuario={usuario} onAtualizado={onAtualizado} />
        <SenhaForm />
        <div className="md:col-span-2">
          <SegurancaMfa usuario={usuario} onAtualizado={onAtualizado} />
        </div>
      </div>
    </section>
  )
}