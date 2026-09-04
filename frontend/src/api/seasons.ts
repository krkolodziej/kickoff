import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import type {
  Fixture,
  GenerateFixturesPayload,
  League,
  PlayerStatisticsRow,
  RosterEntry,
  RosterEntryPayload,
  Season,
  SeasonPayload,
  SeasonTeam,
  StandingRow,
} from './types'

/** The four ids that address anything inside a season. */
export interface SeasonPath {
  organizationId: number
  leagueId: number
  seasonId: number
}

function leaguePath(organizationId: number, leagueId: number): string {
  return `/organizations/${organizationId}/leagues/${leagueId}`
}

function seasonPath({ organizationId, leagueId, seasonId }: SeasonPath): string {
  return `${leaguePath(organizationId, leagueId)}/seasons/${seasonId}`
}

export function useLeague(organizationId: number, leagueId: number) {
  return useQuery({
    queryKey: qk.league(organizationId, leagueId),
    queryFn: () => apiFetch<League>(leaguePath(organizationId, leagueId)),
  })
}

export function useSeasons(organizationId: number, leagueId: number) {
  return useQuery({
    queryKey: qk.seasons(organizationId, leagueId),
    queryFn: () => apiFetch<Season[]>(`${leaguePath(organizationId, leagueId)}/seasons`),
  })
}

export function useSeason(path: SeasonPath) {
  return useQuery({
    queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
    queryFn: () => apiFetch<Season>(seasonPath(path)),
  })
}

export function useCreateSeason(organizationId: number, leagueId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SeasonPayload) =>
      apiFetch<Season>(`${leaguePath(organizationId, leagueId)}/seasons`, {
        method: 'POST',
        body: payload,
      }),
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: qk.seasons(organizationId, leagueId) }),
  })
}

export function useSeasonTeams(path: SeasonPath) {
  return useQuery({
    queryKey: qk.seasonTeams(path.organizationId, path.leagueId, path.seasonId),
    queryFn: () => apiFetch<SeasonTeam[]>(`${seasonPath(path)}/teams`),
  })
}

export function useRegisterTeam(path: SeasonPath) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (teamId: number) =>
      apiFetch<SeasonTeam>(`${seasonPath(path)}/teams`, {
        method: 'POST',
        body: { team_id: teamId },
      }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
      }),
  })
}

export function useWithdrawTeam(path: SeasonPath) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (seasonTeamId: number) =>
      apiFetch<void>(`${seasonPath(path)}/teams/${seasonTeamId}`, { method: 'DELETE' }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
      }),
  })
}

export function useRoster(path: SeasonPath, seasonTeamId: number | null) {
  return useQuery({
    queryKey: qk.roster(path.organizationId, path.leagueId, path.seasonId, seasonTeamId ?? 0),
    queryFn: () => apiFetch<RosterEntry[]>(`${seasonPath(path)}/teams/${seasonTeamId}/roster`),
    // Nothing to fetch until a club has been picked. Without this the query would fire with
    // a null id and answer 404 on every visit to the page.
    enabled: seasonTeamId !== null,
  })
}

function useSquadMutation<TVariables>(
  path: SeasonPath,
  mutationFn: (variables: TVariables) => Promise<unknown>,
) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    // The squad *and* the club list, because squad_size lives on the latter.
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
      }),
  })
}

export function useAddToSquad(path: SeasonPath, seasonTeamId: number) {
  return useSquadMutation<RosterEntryPayload>(path, (payload) =>
    apiFetch<RosterEntry>(`${seasonPath(path)}/teams/${seasonTeamId}/roster`, {
      method: 'POST',
      body: payload,
    }),
  )
}

export function useUpdateSquadEntry(path: SeasonPath, seasonTeamId: number) {
  return useSquadMutation<{ rosterEntryId: number; payload: RosterEntryPayload }>(
    path,
    ({ rosterEntryId, payload }) =>
      apiFetch<RosterEntry>(`${seasonPath(path)}/teams/${seasonTeamId}/roster/${rosterEntryId}`, {
        method: 'PATCH',
        body: payload,
      }),
  )
}

export function useRemoveFromSquad(path: SeasonPath, seasonTeamId: number) {
  return useSquadMutation<number>(path, (rosterEntryId) =>
    apiFetch<void>(`${seasonPath(path)}/teams/${seasonTeamId}/roster/${rosterEntryId}`, {
      method: 'DELETE',
    }),
  )
}

/**
 * The squad of one registered club, addressed by the club rather than by the season-team row.
 *
 * The match screen knows which clubs are playing, not which SeasonTeam rows they correspond
 * to, so this resolves that in one place instead of at every call site.
 */
export function useSquadOf(path: SeasonPath, teamId: number | null) {
  const seasonTeams = useSeasonTeams(path)
  const seasonTeam = seasonTeams.data?.find((row) => row.team_id === teamId)

  const roster = useRoster(path, seasonTeam?.id ?? null)

  return { players: roster.data ?? [], isPending: seasonTeams.isPending || roster.isPending }
}

export function useFixtures(path: SeasonPath) {
  return useQuery({
    queryKey: qk.fixtures(path.organizationId, path.leagueId, path.seasonId),
    queryFn: () => apiFetch<Fixture[]>(`${seasonPath(path)}/fixtures`),
  })
}

/**
 * The league table. Read-only, because there is nothing to write: the server works it out
 * from finished matches on every request rather than storing a second copy of the results.
 */
export function useStandings(path: SeasonPath) {
  return useQuery({
    queryKey: qk.standings(path.organizationId, path.leagueId, path.seasonId),
    queryFn: () => apiFetch<StandingRow[]>(`${seasonPath(path)}/standings`),
  })
}

export function usePlayerStatistics(path: SeasonPath) {
  return useQuery({
    queryKey: qk.playerStatistics(path.organizationId, path.leagueId, path.seasonId),
    queryFn: () => apiFetch<PlayerStatisticsRow[]>(`${seasonPath(path)}/statistics`),
  })
}

export function useGenerateFixtures(path: SeasonPath) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: GenerateFixturesPayload) =>
      apiFetch<Fixture[]>(`${seasonPath(path)}/fixtures/generate`, {
        method: 'POST',
        body: payload,
      }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
      }),
  })
}

export function useClearFixtures(path: SeasonPath) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => apiFetch<void>(`${seasonPath(path)}/fixtures`, { method: 'DELETE' }),
    onSuccess: () =>
      queryClient.invalidateQueries({
        queryKey: qk.season(path.organizationId, path.leagueId, path.seasonId),
      }),
  })
}
