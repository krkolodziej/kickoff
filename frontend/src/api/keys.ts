import type { ListParams } from './types'

/**
 * Query keys, built as a factory rather than written inline.
 *
 * They nest on purpose. Everything under an organization hangs off `qk.organization(id)`, so
 * one mutation can invalidate the whole subtree with a single call instead of a list of keys
 * that will drift out of date.
 */
export const qk = {
  currentUser: ['auth', 'current-user'] as const,

  organizations: (search?: string) =>
    search ? (['organizations', { search }] as const) : (['organizations'] as const),

  organization: (organizationId: number) => ['organizations', organizationId] as const,

  members: (organizationId: number) => ['organizations', organizationId, 'members'] as const,

  leagues: (organizationId: number, params?: ListParams) =>
    ['organizations', organizationId, 'leagues', params ?? {}] as const,

  teams: (organizationId: number, params?: ListParams) =>
    ['organizations', organizationId, 'teams', params ?? {}] as const,

  players: (organizationId: number, params?: ListParams) =>
    ['organizations', organizationId, 'players', params ?? {}] as const,

  league: (organizationId: number, leagueId: number) =>
    ['organizations', organizationId, 'leagues', leagueId] as const,

  seasons: (organizationId: number, leagueId: number) =>
    [...qk.league(organizationId, leagueId), 'seasons'] as const,

  // Everything below a season hangs off this key, so registering a club or editing a squad
  // can invalidate the lot with one call — and so can generating a calendar, next stage.
  season: (organizationId: number, leagueId: number, seasonId: number) =>
    [...qk.seasons(organizationId, leagueId), seasonId] as const,

  seasonTeams: (organizationId: number, leagueId: number, seasonId: number) =>
    [...qk.season(organizationId, leagueId, seasonId), 'teams'] as const,

  roster: (organizationId: number, leagueId: number, seasonId: number, seasonTeamId: number) =>
    [...qk.seasonTeams(organizationId, leagueId, seasonId), seasonTeamId, 'roster'] as const,

  fixtures: (organizationId: number, leagueId: number, seasonId: number) =>
    [...qk.season(organizationId, leagueId, seasonId), 'fixtures'] as const,

  fixture: (organizationId: number, leagueId: number, seasonId: number, fixtureId: number) =>
    [...qk.fixtures(organizationId, leagueId, seasonId), fixtureId] as const,

  // Both hang off the season key, so finishing a match invalidates the table and the scorer
  // list along with everything else under that season — one call, no list to keep in step.
  standings: (organizationId: number, leagueId: number, seasonId: number) =>
    [...qk.season(organizationId, leagueId, seasonId), 'standings'] as const,

  playerStatistics: (organizationId: number, leagueId: number, seasonId: number) =>
    [...qk.season(organizationId, leagueId, seasonId), 'statistics'] as const,

  matchEvents: (organizationId: number, leagueId: number, seasonId: number, fixtureId: number) =>
    [...qk.fixture(organizationId, leagueId, seasonId, fixtureId), 'events'] as const,

  // Outside the organization subtree on purpose: the bell belongs to the person, not to any
  // one organization, and it must not be swept away when a season's cache is invalidated.
  notifications: (unreadOnly = false) => ['notifications', { unreadOnly }] as const,

  unreadNotifications: ['notifications', 'unread-count'] as const,
} as const
