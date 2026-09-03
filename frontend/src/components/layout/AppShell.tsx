import { LogOut } from 'lucide-react'
import { Link, Outlet } from 'react-router-dom'

import { useCurrentUser, useLogout } from '@/api/auth'
import { BrandMark } from '@/components/layout/BrandMark'
import { ThemeToggle } from '@/components/layout/ThemeToggle'
import { Button } from '@/components/ui/button'

function initialsOf(name: string, fallback: string): string {
  const parts = name.split(' ').filter(Boolean)

  if (parts.length === 0) {
    return fallback.slice(0, 2).toUpperCase()
  }

  return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase()
}

export function AppShell() {
  const { user } = useCurrentUser()
  const logout = useLogout()

  return (
    <div className="min-h-dvh bg-background">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-[var(--radius-control)] focus:bg-surface focus:px-4 focus:py-2 focus:shadow-lg"
      >
        Skip to content
      </a>

      <header className="sticky top-0 z-40 border-b border-border bg-background/80 backdrop-blur-md">
        <div className="mx-auto flex h-16 max-w-6xl items-center gap-4 px-5">
          <Link to="/dashboard" className="flex items-center gap-2.5 rounded-md">
            <BrandMark />
            <span className="text-[17px] font-semibold tracking-tight">Kickoff</span>
          </Link>

          <div className="ml-auto flex items-center gap-3">
            <ThemeToggle />

            {user ? (
              <>
                <span
                  aria-hidden="true"
                  className="grid size-8 place-items-center rounded-full bg-primary-wash text-[11px] font-semibold text-primary"
                >
                  {initialsOf(user.full_name, user.email)}
                </span>

                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => logout.mutate()}
                  disabled={logout.isPending}
                >
                  <LogOut className="size-3.5" />
                  Sign out
                </Button>
              </>
            ) : null}
          </div>
        </div>
      </header>

      <main id="main" className="mx-auto max-w-6xl px-5 py-10">
        <Outlet />
      </main>
    </div>
  )
}
