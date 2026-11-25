/**
 * Funções finas sobre `api` para as rotas de escalas (E07). Cada uma só
 * monta a chamada e devolve `{ data, error }` como o cliente; as telas leem o
 * erro com `lerErro` para mostrar o `code` do problem document.
 */
import { api } from './client'
import { cabecalhos } from './sessao'
import type { components } from './schema'

type Escala = components['schemas']['Escala']
type EscalaResumo = components['schemas']['EscalaResumo']
type EscalaMembro = components['schemas']['EscalaMembro']
type ConflitoEscala = components['schemas']['ConflitoEscala']
type MinhaEscala = components['schemas']['MinhaEscala']
type TrocaEscala = components['schemas']['TrocaEscala']

type MembroDaEquipe = components['schemas']['MembroEquipe']
type Equipe = components['schemas']['Equipe']
type EquipeResumo = components['schemas']['EquipeResumo']
export type { MembroDaEquipe, Equipe, EquipeResumo }

/** Equipes de escala do EBI (uma por coordenadora). */
export async function listarEquipes(
  ebiId: string,
  signal?: AbortSignal,
): Promise<{ data?: EquipeResumo[]; error?: unknown }> {
  const { data, error } = await api.GET('/ebis/{id}/equipes', {
    params: { path: { id: ebiId } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data, error }
}

/** Equipe com os membros e se cada um ainda tem vínculo. */
export async function obterEquipe(
  equipeId: string,
  signal?: AbortSignal,
): Promise<{ data?: Equipe; error?: unknown }> {
  const { data, error } = await api.GET('/equipes/{id}', {
    params: { path: { id: equipeId } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data, error }
}

export async function criarEquipe(
  ebiId: string,
  coordenadoraId: string,
): Promise<{ data?: Equipe; error?: unknown }> {
  const { data, error } = await api.POST('/ebis/{id}/equipes', {
    params: { path: { id: ebiId } },
    headers: cabecalhos(),
    body: { coordenadora_id: coordenadoraId },
  })
  return { data: data?.data, error }
}

export async function incluirMembroEquipe(
  equipeId: string,
  usuarioId: string,
): Promise<{ data?: Equipe; error?: unknown }> {
  const { data, error } = await api.POST('/equipes/{id}/members', {
    params: { path: { id: equipeId } },
    headers: cabecalhos(),
    body: { usuario_id: usuarioId },
  })
  return { data: data?.data, error }
}

export async function retirarMembroEquipe(
  equipeId: string,
  usuarioId: string,
): Promise<{ data?: Equipe; error?: unknown }> {
  const { data, error } = await api.DELETE('/equipes/{id}/members/{usuarioId}', {
    params: { path: { id: equipeId, usuarioId } },
    headers: cabecalhos(),
  })
  return { data: data?.data, error }
}

/** Sessões do EBI no período com a situação da escala. */
export async function listarEscalas(
  ebiId: string,
  inicio: string,
  fim: string,
  signal?: AbortSignal,
): Promise<{ data?: EscalaResumo[]; error?: unknown }> {
  const { data, error } = await api.GET('/ebis/{id}/schedules', {
    params: { path: { id: ebiId }, query: { inicio, fim } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data, error }
}

/** Equipe do EBI que pode ser escalada. */
export async function equipeDoEbi(
  ebiId: string,
  signal?: AbortSignal,
): Promise<{ data?: MembroDaEquipe[]; error?: unknown }> {
  const { data, error } = await api.GET('/ebis/{id}/team', {
    params: { path: { id: ebiId } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data as MembroDaEquipe[] | undefined, error }
}

/** Detalhe da escala de uma sessão (membros, conflitos e equipe mínima). */
export async function obterEscala(
  sessaoId: string,
  signal?: AbortSignal,
): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.GET('/schedules/{id}', {
    params: { path: { id: sessaoId } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.escala, error }
}

export async function criarEscala(
  sessaoId: string,
  estimativa: number,
  membros: { usuario_id: string; funcao: 'coordenadora' | 'auxiliar' }[] = [],
  equipeId = '', // TODO(Tarefa 13): substituir pela equipe da dona
): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.POST('/sessions/{id}/schedules', {
    params: { path: { id: sessaoId } },
    headers: cabecalhos(),
    body: { equipe_id: equipeId, estimativa_participantes: estimativa, membros },
  })
  return { data: data?.escala, error }
}

export async function alterarEstimativa(
  escalaId: string,
  estimativa: number,
): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.PATCH('/schedules/{id}', {
    params: { path: { id: escalaId } },
    headers: cabecalhos(),
    body: { estimativa_participantes: estimativa },
  })
  return { data: data?.escala, error }
}

export async function incluirMembro(
  escalaId: string,
  usuarioId: string,
  funcao: 'coordenadora' | 'auxiliar',
): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.POST('/schedules/{id}/members', {
    params: { path: { id: escalaId } },
    headers: cabecalhos(),
    body: { usuario_id: usuarioId, funcao },
  })
  return { data: data?.escala, error }
}

export async function retirarMembro(membroId: string): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.DELETE('/schedule-members/{id}', {
    params: { path: { id: membroId } },
    headers: cabecalhos(),
  })
  return { data: data?.escala, error }
}

export async function aprovarEscala(escalaId: string): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.POST('/schedules/{id}/approve', {
    params: { path: { id: escalaId } },
    headers: cabecalhos(),
  })
  return { data: data?.escala, error }
}

/** Sugestão de presença da equipe: só as vagas informadas mudam. */
export async function registrarPresencaEquipe(
  escalaId: string,
  membros: { id: string; presente: boolean }[],
): Promise<{ data?: Escala; error?: unknown }> {
  const { data, error } = await api.PUT('/schedules/{id}/team-attendance', {
    params: { path: { id: escalaId } },
    headers: cabecalhos(),
    body: { membros },
  })
  return { data: data?.escala, error }
}

/** Minhas escalas no período (não exige `escala.ler`). */
export async function minhasEscalas(
  inicio: string,
  fim: string,
  signal?: AbortSignal,
): Promise<{ data?: MinhaEscala[]; error?: unknown }> {
  const { data, error } = await api.GET('/me/schedules', {
    params: { query: { inicio, fim } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data, error }
}

export async function confirmarVaga(
  membroId: string,
): Promise<{ data?: EscalaMembro; conflitos?: ConflitoEscala[]; error?: unknown }> {
  const { data, error } = await api.POST('/schedule-members/{id}/confirm', {
    params: { path: { id: membroId } },
    headers: cabecalhos(),
  })
  return { data: data?.membro as EscalaMembro | undefined, conflitos: data?.conflitos, error }
}

export async function pedirTroca(
  escalaMembroId: string,
  motivo?: string,
): Promise<{ data?: TrocaEscala; error?: unknown }> {
  const { data, error } = await api.POST('/schedule-swaps', {
    headers: cabecalhos(),
    body: { escala_membro_id: escalaMembroId, motivo: motivo?.trim() || null },
  })
  return { data: data?.troca, error }
}

export async function cancelarTroca(trocaId: string): Promise<{ data?: TrocaEscala; error?: unknown }> {
  const { data, error } = await api.POST('/schedule-swaps/{id}/cancel', {
    params: { path: { id: trocaId } },
    headers: cabecalhos(),
  })
  return { data: data?.troca, error }
}

/** Trocas do EBI (para quem tem `troca.decidir`). */
export async function listarTrocas(
  ebiId: string,
  signal?: AbortSignal,
): Promise<{ data?: TrocaEscala[]; error?: unknown }> {
  const { data, error } = await api.GET('/schedule-swaps', {
    params: { query: { ebi_id: ebiId } },
    headers: cabecalhos(),
    signal,
  })
  return { data: data?.data, error }
}

export async function decidirTroca(
  trocaId: string,
  decisao: 'aprovar' | 'recusar',
  substitutoUsuarioId?: string,
  motivo?: string,
): Promise<{ data?: TrocaEscala; error?: unknown }> {
  const { data, error } = await api.PATCH('/schedule-swaps/{id}', {
    params: { path: { id: trocaId } },
    headers: cabecalhos(),
    body: {
      decisao,
      substituto_usuario_id: substitutoUsuarioId ?? null,
      motivo: motivo?.trim() || null,
    },
  })
  return { data: data?.troca, error }
}
