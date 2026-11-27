import { useEffect, useState } from 'react'
import { ArrowRightIcon, CakeIcon, ShieldAlertIcon } from 'lucide-react'

import { api } from '../api/client'
import type { components } from '../api/schema'
import { cabecalhos, type ContextoAtuacao, type Usuario } from '../api/sessao'
import { itensVisiveis, type IdTela } from '@/components/layout/ListaNav'
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatarData, primeiroNome, rotuloPapel, rotuloTipoEscopo } from '@/lib/formatos'

type AlertaDozeAnos = components['schemas']['AlertaDozeAnos']

/** Quem faz 12 anos nos próximos 30 dias na igreja do EBI (CA4, CA5). */
function FazemDozeAnos({
  escopo,
  onSelecionarTela,
}: {
  escopo: ContextoAtuacao
  onSelecionarTela: (tela: IdTela) => void
}) {
  const [alertas, setAlertas] = useState<AlertaDozeAnos[] | null>(null)
  const [erro, setErro] = useState(false)

  useEffect(() => {
    let ativo = true
    setAlertas(null)
    setErro(false)
    void api
      .GET('/ebis/{id}/age-alerts', {
        params: { path: { id: escopo.escopo_id ?? '' }, query: { finalidade: 'aviso_12_anos' } },
        headers: cabecalhos(undefined, escopo),
      })
      .then(({ data, error }) => {
        if (!ativo) return
        if (error || !data) setErro(true)
        else setAlertas(data.data)
      })
    return () => {
      ativo = false
    }
  }, [escopo])

  return (
    <Card data-testid="aniversarios-12">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <CakeIcon className="size-5" aria-hidden="true" />
          Fazem 12 anos nos próximos 30 dias
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {erro && (
          <Alert variant="destructive" role="alert">
            <AlertDescription>Não foi possível carregar os aniversários.</AlertDescription>
          </Alert>
        )}
        {alertas?.length === 0 && (
          <p className="text-sm text-muted-foreground">Ninguém faz 12 anos nos próximos 30 dias.</p>
        )}
        {alertas && alertas.length > 0 && (
          <>
            <ul className="flex flex-col gap-2">
              {alertas.map((a) => (
                <li key={a.participante_id} className="flex flex-wrap items-center gap-x-3 text-sm">
                  <span className="font-medium">{a.nome}</span>
                  <code>{a.matricula}</code>
                  <span>{formatarData(a.faz_12_em)}</span>
                </li>
              ))}
            </ul>
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="self-start"
              onClick={() => onSelecionarTela('excecao')}
            >
              Exceção inclusiva
            </Button>
          </>
        )}
      </CardContent>
    </Card>
  )
}

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

      {escopo?.tipo_escopo === 'ebi' &&
        ['coordenadora', 'coordenadora_chefe'].includes(escopo.papel ?? '') && (
          <FazemDozeAnos escopo={escopo} onSelecionarTela={onSelecionarTela} />
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
