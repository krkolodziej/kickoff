import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import type { SeasonPath } from './seasons'
import { isLive, type Fixture, type MatchEvent, type MatchEventPayload, type MatchStatus } from './types'

function fixturePath(path: SeasonPath, fixtureId: number): string {
  return `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}/fixtures/${fixtureId}`
}

/**
 * The live screen, today.
 *
 * Polling every three seconds is not a placeholder anybody should be embarrassed by: a
 * football match produces an event every few minutes, and this needs no extra process, no
 * hub, and no reconnection logic. Stage 8 swaps the transport for Mercure **behind this same
 * hook**, so no component has to know which one is in use — and polling stays as the fallback
 * for a checkout with no hub running.
 */
export function useMatch(path: SeasonPath, fixtureId: number) {
  return useQuery({
    queryKey: qk.fixture(path.organizationId, path.leagueId, path.seasonId, fixtureId),
    queryFn: () => apiFetch<Fixture>(fixturePath(path, fixtureId)),
    refetchInterval: (query) => (query.state.data && isLive(query.state.data) ? 3000 : false),
  })
}

export function useMatchEvents(path: SeasonPath, fixtureId: number, live: boolean) {
  return useQuery({
    queryKey: qk.matchEvents(path.organizationId, path.leagueId, path.seasonId, fixtureId),
    queryFn: () => apiFetch<MatchEvent[]>(`${fixturePath(path, fixtureId)}/events`),
    refetchInterval: live ? 3000 : false,
  })
}

function useMatchMutation<TVariables>(
  path: SeasonPath,
  fixtureId: number,
  mutationFn: (variables: TVariables) => Promise<unknown>,
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    // The fixture key covers the match and its events; the season key covers the calendar
    // listing, which shows the score too. Hierarchical keys make that two calls, not six.
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: qk.fixture(path.organizationId, path.leagueId, path.seasonId, fixtureId),
      })
      await queryClient.invalidateQueries({
        queryKey: qk.fixtures(path.organizationId, path.leagueId, path.seasonId),
      })
    },
  })
}

const TRANSITION_PATHS: Record<MatchStatus, string> = {
  LIVE: 'start',
  FINISHED: 'finish',
  CANCELLED: 'cancel',
  POSTPONED: 'postpone',
  SCHEDULED: 'reschedule',
}

export function useMatchTransition(path: SeasonPath, fixtureId: number) {
  return useMatchMutation<MatchStatus>(path, fixtureId, (target) =>
    apiFetch<Fixture>(`${fixturePath(path, fixtureId)}/${TRANSITION_PATHS[target]}`, {
      method: 'POST',
    }),
  )
}

export function useRecordEvent(path: SeasonPath, fixtureId: number) {
  return useMatchMutation<MatchEventPayload>(path, fixtureId, (payload) =>
    apiFetch<MatchEvent>(`${fixturePath(path, fixtureId)}/events`, {
      method: 'POST',
      body: payload,
    }),
  )
}
