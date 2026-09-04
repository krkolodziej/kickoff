/**
 * Kick-offs are instants and are formatted in the reader's own timezone — a fixture at 15:00
 * in Rzeszów should read 14:00 to somebody in London, because that is when it starts for
 * them. Dates that are dates arrive as `YYYY-MM-DD` from the API and are left alone.
 */
// `hour: 'numeric'` rather than '2-digit': in a 12-hour locale the latter renders "03:00 PM",
// and a leading zero on a 12-hour clock reads as a bug. A 24-hour locale still gets "15:00",
// because the format is left to the reader rather than imposed.
const KICK_OFF = new Intl.DateTimeFormat(undefined, {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
  hour: 'numeric',
  minute: '2-digit',
})

const DAY = new Intl.DateTimeFormat(undefined, {
  weekday: 'short',
  day: 'numeric',
  month: 'short',
})

export function formatKickOff(value: string | null): string {
  if (value === null) {
    return 'To be arranged'
  }

  return KICK_OFF.format(new Date(value))
}

export function formatKickOffDay(value: string | null): string {
  if (value === null) {
    return 'Date to be arranged'
  }

  return DAY.format(new Date(value))
}

export function formatTime(value: string | null): string {
  if (value === null) {
    return '--:--'
  }

  return new Date(value).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
}

/**
 * "3 minutes ago", in the reader's language.
 *
 * `Intl.RelativeTimeFormat` rather than a hand-rolled ladder of ifs, because the plural rules
 * for "2 minutes" are not the same in every language and getting them wrong is the kind of
 * detail that makes an interface feel translated rather than written.
 *
 * The largest unit that fits is chosen, and anything older than a week falls back to a date:
 * "37 days ago" is arithmetic, not information.
 */
const RELATIVE = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['second', 1000],
  ['minute', 60 * 1000],
  ['hour', 60 * 60 * 1000],
  ['day', 24 * 60 * 60 * 1000],
]

export function formatRelative(value: string, now: Date = new Date()): string {
  const then = new Date(value)
  const elapsed = now.getTime() - then.getTime()

  if (elapsed >= 7 * UNITS[3][1]) {
    return DAY.format(then)
  }

  let chosen: [Intl.RelativeTimeFormatUnit, number] = UNITS[0]

  for (const unit of UNITS) {
    if (Math.abs(elapsed) >= unit[1]) {
      chosen = unit
    }
  }

  return RELATIVE.format(-Math.round(elapsed / chosen[1]), chosen[0])
}
