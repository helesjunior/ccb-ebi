import { ArrowRightIcon, ShieldAlertIcon } from 'lucide-react'

import type { ContextoAtuacao, Usuario } from '../api/sessao'
import { itensVisiveis, type IdTela } from '@/components/layout/ListaNav'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { primeiroNome, rotuloPapel, rotuloTipoEscopo } from '@/lib/formatos'

/**
 * Tela inicial: saudação, papéis do usuário e atalhos para as telas que ele
 * pode usar (os mesmos itens da navegação, já filtrados por papel).
 */
export function Inicio({
  usuario,
  escopo,
  onSelecionarTela,
}: {
  usuario: Usuario
  escopo: ContextoAtuacao | null
  onSelecionarTela: (tela: IdTela) => void
}) {
  const papeis = usuario.papeis ?? []
  const atalhos = itensVisiveis(papeis).filter((item) => item.id !== 'inicio')

  return (
    <section className="mx-auto flex max-w-5xl flex-col gap-8">
      <div className="flex flex-col gap-3">
        <h2 className="text-2xl font-semibold tracking-tight md:text-3xl">
          Olá, {primeiroNome(usuario.nome) || 'bem-vindo(a)'}!
        </h2>
        <div className="flex flex-col gap-1 text-sm text-muted-foreground">
          <p data-testid="usuario-nome" className="font-medium text-foreground">
            {usuario.nome}
          </p>
          <p>{usuario.email}</p>
        </div>
        {papeis.length > 0 && (
          <ul className="flex flex-wrap gap-2" aria-label="Seus papéis">
            {papeis.map((p) => (
              <li key={p}>
                <Badge variant="secondary">{rotuloPapel(p)}</Badge>
              </li>
            ))}
          </ul>
        )}
        {escopo && (
          <p className="text-sm text-muted-foreground">
            Atuando como <strong>{rotuloPapel(escopo.papel)}</strong> ·{' '}
            {rotuloTipoEscopo(escopo.tipo_escopo)}
          </p>
        )}
      </div>

      {usuario.mfa_ativo === false && (
        <Alert>
          <ShieldAlertIcon aria-hidden="true" />
          <AlertTitle>Proteja sua conta</AlertTitle>
          <AlertDescription className="flex flex-col items-start gap-2">
            Ative a verificação em duas etapas para dificultar o acesso de terceiros.
            <Button type="button" size="sm" variant="outline" onClick={() => onSelecionarTela('perfil')}>
              Ativar agora
            </Button>
          </AlertDescription>
        </Alert>
      )}

      <div className="flex flex-col gap-3">
        <h3 className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
          Acesso rápido
        </h3>
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {atalhos.map((item) => {
            const Icone = item.icone
            return (
              <li key={item.id}>
                <button
                  type="button"
                  onClick={() => onSelecionarTela(item.id)}
                  className="group flex h-full w-full items-start gap-4 rounded-xl border bg-card p-4 text-left shadow-xs transition hover:border-primary/40 hover:bg-primary/5 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Icone className="size-5" aria-hidden="true" />
                  </span>
                  <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex items-center gap-1 font-medium">
                      {item.rotulo}
                      <ArrowRightIcon
                        className="size-4 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100"
                        aria-hidden="true"
                      />
                    </span>
                    <span className="text-sm text-muted-foreground">{item.descricao}</span>
                  </span>
                </button>
              </li>
            )
          })}
        </ul>
      </div>
    </section>
  )
}
