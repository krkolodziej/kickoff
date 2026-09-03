import type { MatchStatus } from '@/api/types'
import { cn } from '@/lib/cn'

/**
 * Five statuses, five *structurally* different treatments — not five colours.
 *
 * Colour alone excludes anyone who cannot distinguish it, and it competes with the two
 * colours this application has reserved for cards. So live gets a pulsing dot, full time is
 * solid, cancelled is struck through, and postponed carries a rule down its left edge. Each
 * reads correctly in greyscale.
 *
 * The pulse is the only self-animating thing in the app, which is what makes it mean
 * something. `motion-reduce` turns it off for anyone who has asked for that.
 */
const TREATMENT: Record<MatchStatus, { label: string; className: string; dot?: boolean }> = {
  SCHEDULED: {
    label: 'Scheduled',
    className: 'border border-border-strong text-foreground-muted',
  },
  LIVE: {
    label: 'Live',
    className: 'bg-primary text-primary-foreground font-semibold',
    dot: true,
  },
  FINISHED: {
    label: 'Full time',
    className: 'bg-foreground text-background',
  },
  CANCELLED: {
    label: 'Cancelled',
    className: 'border border-border text-foreground-subtle line-through',
  },
  POSTPONED: {
    label: 'Postponed',
    className: 'border-l-[3px] border-l-foreground-muted bg-surface-muted text-foreground-muted',
  },
}

export function MatchStatusBadge({
  status,
  className,
}: {
  status: MatchStatus
  className?: string
}) {
  const { label, className: tone, dot } = TREATMENT[status]

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] uppercase tracking-wide',
        tone,
        className,
      )}
    >
      {dot ? (
        <span className="size-1.5 animate-pulse rounded-full bg-current motion-reduce:animate-none" />
      ) : null}
      {label}
    </span>
  )
}
