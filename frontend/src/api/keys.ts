/**
 * Query keys, built as a factory rather than written inline.
 *
 * They nest on purpose. Later stages hang leagues, fixtures and standings off an
 * organization key, so one mutation can invalidate everything derived from it with a single
 * `invalidateQueries({ queryKey: qk.organization(id) })` instead of a list of keys that will
 * drift out of date.
 */
export const qk = {
  currentUser: ['auth', 'current-user'] as const,

  organizations: (search?: string) =>
    search ? (['organizations', { search }] as const) : (['organizations'] as const),

  organization: (organizationId: number) => ['organizations', organizationId] as const,

  members: (organizationId: number) => ['organizations', organizationId, 'members'] as const,
} as const
