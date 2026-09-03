import { Navigate, Outlet, useLocation } from 'react-router-dom'

import { useCurrentUser } from '@/api/auth'

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

export function RequireAnonymous() {
  const { user, isResolving } = useCurrentUser()

  if (isResolving) {
    return <Resolving label="Checking your session" />
  }

  if (user) {
    return <Navigate to="/dashboard" replace />
  }

  return <Outlet />
}
