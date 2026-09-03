import { ArrowLeftRight } from 'lucide-react'

import type { MatchEvent, MatchEventType } from '@/api/types'
import { cn } from '@/lib/cn'

/**
 * The mark for an event.
 *
 * Yellow and red appear here and **only** here in the whole application — the two colours are
 * reserved for cards precisely so that a booking is never confused with an error state. A
 * goal is a ring with a dot rather than a colour, so it survives greyscale and colour
 * blindness alike.
 */
function EventMark({ type }: { type: MatchEventType }) {
  switch (type) {
    case 'GOAL':
      return (
        <span
          aria-hidden="true"
          className="grid size-4 place-items-center rounded-full border-2 border-foreground"
        >
          <span className="size-1.5 rounded-full bg-foreground" />
        </span>
      )
    case 'YELLOW_CARD':
      return <span aria-hidden="true" className="h-4 w-3 rounded-[2px] bg-booking" />
    case 'RED_CARD':
      return <span aria-hidden="true" className="h-4 w-3 rounded-[2px] bg-sending-off" />
    case 'SUBSTITUTION':
      return <ArrowLeftRight aria-hidden="true" className="size-4 text-foreground-muted" />
  }
}

const LABEL: Record<MatchEventType, string> = {
  GOAL: 'Goal',
  YELLOW_CARD: 'Yellow card',
  RED_CARD: 'Red card',
  SUBSTITUTION: 'Substitution',
}

function EventRow({ event }: { event: MatchEvent }) {
  const detail =
    event.type === 'SUBSTITUTION' && event.related_player_name !== null
      ? `${event.player_name} → ${event.related_player_name}`
      : event.player_name

  return (
    <li className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 py-2">
      {/* Home events sit left of the minute, away events right — so the eye can follow one
          club down the page without reading every line. */}
      <div className={cn('flex items-center gap-2', event.home ? 'justify-end' : 'invisible')}>
        <span className="truncate text-sm">{detail}</span>
        <EventMark type={event.type} />
      </div>

      <span className="tabular w-10 shrink-0 rounded-full bg-surface-muted py-0.5 text-center text-[11px] font-semibold text-foreground-muted">
        {event.minute}&apos;
      </span>

      <div className={cn('flex items-center gap-2', event.home ? 'invisible' : 'justify-start')}>
        <EventMark type={event.type} />
        <span className="truncate text-sm">{detail}</span>
      </div>

      <span className="sr-only">
        {LABEL[event.type]}, minute {event.minute}: {detail}
      </span>
    </li>
  )
}

export function MatchTimeline({ events }: { events: MatchEvent[] }) {
  if (events.length === 0) {
    return (
      <p className="py-8 text-center text-sm text-foreground-muted">Nothing has happened yet.</p>
    )
  }

  return (
    <ol className="relative divide-y divide-border">
      {events.map((event) => (
        <EventRow key={event.id} event={event} />
      ))}
    </ol>
  )
}
