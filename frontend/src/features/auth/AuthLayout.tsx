import type { ReactNode } from 'react'
import { Outlet } from 'react-router-dom'

import { BrandMark } from '@/components/layout/BrandMark'
import { ThemeToggle } from '@/components/layout/ThemeToggle'

export function AuthLayout() {
  return (
    <div className="relative grid min-h-dvh place-items-center overflow-hidden bg-background px-5 py-12">
      {/* A single soft wash of the brand colour, behind everything, so the sign-in screen
          does not read as a blank page with a box on it. */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 -top-40 h-96 bg-[radial-gradient(60%_100%_at_50%_0%,var(--primary-wash),transparent_70%)]"
      />

      <div className="absolute right-5 top-5">
        <ThemeToggle />
      </div>

      <div className="relative w-full max-w-[26rem]">
        <div className="mb-7 flex items-center gap-2.5">
          <BrandMark className="size-8" />
          <span className="text-lg font-semibold tracking-tight">Kickoff</span>
        </div>

        <Outlet />
      </div>
    </div>
  )
}

export function AuthCard({
  title,
  subtitle,
  children,
  footer,
}: {
  title: string
  subtitle: string
  children: ReactNode
  footer: ReactNode
}) {
  return (
    <div className="surface-panel p-7">
      <h1 className="text-[22px]">{title}</h1>
      <p className="mt-1 text-sm text-foreground-muted">{subtitle}</p>

      <div className="mt-6">{children}</div>

      <p className="mt-6 border-t border-border pt-5 text-[13px] text-foreground-muted">{footer}</p>
    </div>
  )
}

export function FormError({ message }: { message: string | null }) {
  if (!message) {
    return null
  }

  return (
    <p
      role="alert"
      className="rounded-[var(--radius-control)] border border-danger/30 bg-danger-wash px-3 py-2 text-[13px] text-danger"
    >
      {message}
    </p>
  )
}
