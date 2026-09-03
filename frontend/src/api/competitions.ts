import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import {
  listQueryString,
  type League,
  type LeaguePayload,
  type ListParams,
  type Player,
  type PlayerPayload,
  type ResultPage,
  type Team,
  type TeamPayload,
} from './types'

/**
 * One place that knows a collection endpoint answers in two shapes.
 *
 * Every screen wants rows and a total; only some care which shape they arrived in. Deciding
 * that here means no component ever has to ask `Array.isArray(data)`.
 */
export interface Rows<T> {
  rows: T[]
  count: number
  page: number
  next: number | null
  previous: number | null
}

function toRows<T>(payload: T[] | ResultPage<T>): Rows<T> {
  if (Array.isArray(payload)) {
    return { rows: payload, count: payload.length, page: 1, next: null, previous: null }
  }

  return {
    rows: payload.results,
    count: payload.count,
    page: payload.page,
    next: payload.next,
    previous: payload.previous,
  }
}

function useCollection<T>(
  queryKey: readonly unknown[],
  path: string,
  params: ListParams | undefined,
) {
  return useQuery({
    queryKey,
    queryFn: async () => toRows(await apiFetch<T[] | ResultPage<T>>(path + listQueryString(params))),
    // A page that briefly shows the previous page's rows while the next one loads reads far
    // better than a table that empties itself on every click.
    placeholderData: (previous) => previous,
  })
}

export function useLeagues(organizationId: number, params?: ListParams) {
  return useCollection<League>(
    qk.leagues(organizationId, params),
    `/organizations/${organizationId}/leagues`,
    params,
  )
}

export function useTeams(organizationId: number, params?: ListParams) {
  return useCollection<Team>(
    qk.teams(organizationId, params),
    `/organizations/${organizationId}/teams`,
    params,
  )
}

export function usePlayers(organizationId: number, params?: ListParams) {
  return useCollection<Player>(
    qk.players(organizationId, params),
    `/organizations/${organizationId}/players`,
    params,
  )
}

function useCreate<TPayload, TResult>(organizationId: number, resource: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: TPayload) =>
      apiFetch<TResult>(`/organizations/${organizationId}/${resource}`, {
        method: 'POST',
        body: payload,
      }),
    // Invalidating the organization key sweeps every list, page and search below it —
    // which is exactly why the keys nest.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.organization(organizationId) }),
  })
}

function useDelete(organizationId: number, resource: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<void>(`/organizations/${organizationId}/${resource}/${id}`, { method: 'DELETE' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.organization(organizationId) }),
  })
}

export const useCreateLeague = (organizationId: number) =>
  useCreate<LeaguePayload, League>(organizationId, 'leagues')
export const useCreateTeam = (organizationId: number) =>
  useCreate<TeamPayload, Team>(organizationId, 'teams')
export const useCreatePlayer = (organizationId: number) =>
  useCreate<PlayerPayload, Player>(organizationId, 'players')

export const useDeleteLeague = (organizationId: number) => useDelete(organizationId, 'leagues')
export const useDeleteTeam = (organizationId: number) => useDelete(organizationId, 'teams')
export const useDeletePlayer = (organizationId: number) => useDelete(organizationId, 'players')
