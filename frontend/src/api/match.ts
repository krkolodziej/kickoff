import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import { useMatchStream } from './realtime'
import type { SeasonPath } from './seasons'
import { isLive, type Fixture, type MatchEvent, type MatchEventPayload, type MatchStatus } from './types'

function fixturePath(path: SeasonPath, fixtureId: number): string {
  return `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}/fixtures/${fixtureId}`
}

/** How often a live match is re-read when there is no stream to tell us it changed. */
const LIVE_POLL_MS = 3000

export function useMatch(path: SeasonPath, fixtureId: number, streaming = false) {
  return useQuery({
    queryKey: qk.fixture(path.organizationId, path.leagueId, path.seasonId, fixtureId),
    queryFn: () => apiFetch<Fixture>(fixturePath(path, fixtureId)),
    refetchInterval: (query) =>
      !streaming && query.state.data && isLive(query.state.data) ? LIVE_POLL_MS : false,
  })
}

export function useMatchEvents(
  path: SeasonPath,
  fixtureId: number,
  live: boolean,
  streaming = false,
) {
  return useQuery({
    queryKey: qk.matchEvents(path.organizationId, path.leagueId, path.seasonId, fixtureId),
    queryFn: () => apiFetch<MatchEvent[]>(`${fixturePath(path, fixtureId)}/events`),
    refetchInterval: live && !streaming ? LIVE_POLL_MS : false,
  })
}

/**
 * A live match, however it happens to be arriving.
 *
 * The whole point of this hook is that **no component knows which transport is in use**. It
 * opens a stream when the build has a hub, falls back to timers when it does not — or when the
 * hub stops answering mid-match — and the page renders the same either way. The transport is
 * returned only so the interface can say so; nothing about the screen depends on it.
 *
 * Polling was never a placeholder to be ashamed of. A football match produces an event every
 * few minutes, and three seconds of latency on a page somebody is watching deliberately is not
 * a problem worth a second process. What the stream buys is the difference between "within
 * three seconds" and "immediately" — worth having, not worth breaking the page for.
 */
export function useLiveMatch(path: SeasonPath, fixtureId: number) {
  const transport = useMatchStream(path, fixtureId)
  const streaming = transport === 'stream'

  const match = useMatch(path, fixtureId, streaming)
  const live = match.data ? isLive(match.data) : false
  const events = useMatchEvents(path, fixtureId, live, streaming)

  return { match, events, live, transport }
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
