import { useQueryClient } from '@tanstack/react-query'
import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { useCurrentUser } from '@/api/auth'
import { qk } from '@/api/keys'
import type { DemoEntry } from '@/api/types'

function Resolving({ label }: { label: string }) {
  return (
    <div role="status" className="grid min-h-dvh place-items-center">
      <span className="sr-only">{label}</span>
      <span className="size-5 animate-spin rounded-full border-2 border-border border-t-primary" />
    </div>
  )
}

/**
 * Guards are a convenience, never a control.
 *
 * They stop the app rendering a screen whose data the user cannot fetch anyway. The actual
 * boundary is on the server: every endpoint checks membership itself, and a user who edits
 * this file in devtools gains nothing but an empty page full of 404s.
 */
export function RequireAuth() {
  const { user, isResolving } = useCurrentUser()
  const location = useLocation()

  if (isResolving) {
    return <Resolving label="Checking your session" />
  }

  if (!user) {
    return <Navigate to="/sign-in" state={{ from: location.pathname }} replace />
  }

  return <Outlet />
}

/**
 * Signed-in people have no business on the sign-in form — and this is the one place that
 * decides where they go instead.
 *
 * That concentration is deliberate. Signing in fills the current-user cache from inside the
 * mutation's own success handler, so this guard re-renders and redirects *before* the form
 * that called it gets a turn to navigate anywhere. Any destination the form tried to choose
 * for itself would simply be overwritten, which is how the `from` path quietly stopped
 * working. So the forms no longer choose: they sign the person in, and whoever created the
 * session leaves the destination where this guard will find it.
 *
 * Two sources, in order. The demonstration button knows a season worth opening and records
 * it; anyone bounced here from a page they were trying to reach gets sent back to it.
 * Everything else is the dashboard.
 */
export function RequireAnonymous() {
  const { user, isResolving } = useCurrentUser()
  const location = useLocation()
  const queryClient = useQueryClient()

  if (isResolving) {
    return <Resolving label="Checking your session" />
  }

  if (!user) {
    return <Outlet />
  }

  const demo = queryClient.getQueryData<DemoEntry | null>(qk.demoEntry)
  const from = (location.state as { from?: string } | null)?.from

  return <Navigate to={demo ? seasonPath(demo) : (from ?? '/dashboard')} replace />
}

/** The season overview — the page the demonstration exists to show. */
function seasonPath({ organization_id, league_id, season_id }: DemoEntry): string {
  return `/organizations/${organization_id}/leagues/${league_id}/seasons/${season_id}/overview`
}
