import type { ReactNode } from 'react'

import { Button } from '@/components/ui/button'

export function LoadingState({ label = 'Loading' }: { label?: string }) {
  return (
    <div role="status" className="flex items-center gap-3 py-10 text-sm text-foreground-muted">
      <span className="size-4 animate-spin rounded-full border-2 border-border border-t-primary" />
      <span>{label}…</span>
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="flex flex-col items-start gap-3 py-10">
      <div>
        <p className="text-[15px] font-semibold">That did not work</p>
        <p className="mt-1 text-sm text-foreground-muted">{message}</p>
      </div>

      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  )
}

/**
 * `action` is required, not optional. An empty state that only says "nothing here" leaves
 * the reader to work out what to do next, and a screen full of those is how an application
 * ends up feeling broken rather than new.
 */
export function EmptyState({
  title,
  description,
  action,
}: {
  title: string
  description: string
  action: ReactNode
}) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-[var(--radius-card)] border border-dashed border-border-strong px-6 py-12 text-center">
      <p className="text-[15px] font-semibold">{title}</p>
      <p className="max-w-sm text-sm text-foreground-muted">{description}</p>
      <div className="mt-1">{action}</div>
    </div>
  )
}
