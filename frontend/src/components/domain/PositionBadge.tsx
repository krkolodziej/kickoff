import type { PlayerPosition } from '@/api/types'
import { cn } from '@/lib/cn'
import { Badge } from '@/components/ui/badge'
import { positionLabel } from '@/lib/positions'

/**
 * A position, in the two letters a squad list has room for.
 *
 * The same principle as `MatchStatusBadge`: the abbreviation carries the meaning and the tone
 * is decoration, so the badge reads correctly in greyscale and to anyone who cannot separate
 * the colours. That is also why none of these is yellow or red — the design system reserves
 * both for cards, and a defender is not a booking.
 */
const TREATMENT: Record<PlayerPosition, { short: string; className: string }> = {
  GOALKEEPER: { short: 'GK', className: 'bg-foreground text-background' },
  DEFENDER: { short: 'DF', className: 'border border-border-strong text-foreground-muted' },
  MIDFIELDER: { short: 'MF', className: 'bg-surface-muted text-foreground-muted' },
  FORWARD: { short: 'FW', className: 'bg-primary-wash text-primary' },
}

export function PositionBadge({
  position,
  className,
}: {
  position: PlayerPosition | null
  className?: string
}) {
  if (position === null) {
    return <span className="text-[13px] text-foreground-subtle">—</span>
  }

  const { short, className: tone } = TREATMENT[position]

  return (
    <span
      // The abbreviation is the visible text; the full word is what a screen reader says,
      // because "DF" read aloud is not a position.
      title={positionLabel(position)}
      className={cn(
        'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide',
        tone,
        className,
      )}
    >
      <span aria-hidden="true">{short}</span>
      <span className="sr-only">{positionLabel(position)}</span>
    </span>
  )
}

/**
 * The armband.
 *
 * The letter is what a sighted reader recognises and the word is what a screen reader says,
 * because "C" read aloud in a list of names is not information.
 */
export function CaptainBadge() {
  return (
    <Badge tone="primary">
      <span aria-hidden="true">C</span>
      <span className="sr-only">Captain</span>
    </Badge>
  )
}
