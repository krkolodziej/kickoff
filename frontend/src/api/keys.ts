/**
 * Query keys, built as a factory rather than written inline.
 *
 * They nest on purpose. Later stages hang fixtures, standings and statistics off a season
 * key, so generating a fixture list can invalidate everything derived from that season with
 * a single `invalidateQueries({ queryKey: qk.season(o, l, s) })` instead of a list of keys
 * that will drift out of date.
 */
export const qk = {
  currentUser: ['auth', 'current-user'] as const,
} as const
