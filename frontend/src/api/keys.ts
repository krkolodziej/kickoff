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
} as const
