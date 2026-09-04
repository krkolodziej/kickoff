import { describe, expect, it } from 'vitest'

import { formatRelative } from './datetime'

/**
 * The bell renders one of these on every row, so it is worth knowing that it picks the unit a
 * reader would pick — and that it stops trying once "days ago" turns into arithmetic.
 *
 * The assertions deliberately do **not** look for English words. `formatRelative` formats in
 * the reader's own locale, so a test matching /minute/ passes in London and fails in Rzeszów
 * — which is a test that reports the machine it runs on rather than the code. What is under
 * test is the choice of value and unit; rendering that pair into a sentence is Intl's job, so
 * the expectation is built from the same pair rather than from a phrase.
 *
 * `now` is a parameter for the same reason the reminder scan takes a clock: a test that works
 * out its own "now" from the source the code uses proves only that two expressions agree.
 */
describe('formatRelative', () => {
  const now = new Date('2026-05-01T12:00:00Z')

  const at = (iso: string) => formatRelative(iso, now)

  const relative = (value: number, unit: Intl.RelativeTimeFormatUnit) =>
    new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(value, unit)

  const asDate = (iso: string) =>
    new Intl.DateTimeFormat(undefined, {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    }).format(new Date(iso))

  it('counts in seconds for something that just happened', () => {
    expect(at('2026-05-01T11:59:50Z')).toBe(relative(-10, 'second'))
  })

  it('switches to minutes once a minute has passed', () => {
    expect(at('2026-05-01T11:57:00Z')).toBe(relative(-3, 'minute'))
  })

  it('switches to hours, and then to days', () => {
    expect(at('2026-05-01T09:00:00Z')).toBe(relative(-3, 'hour'))
    expect(at('2026-04-29T12:00:00Z')).toBe(relative(-2, 'day'))
  })

  /**
   * Past a week the relative form stops informing anybody: "37 days ago" is a subtraction the
   * reader then has to undo, so the date itself is more useful.
   */
  it('gives up and shows a date once it is more than a week old', () => {
    expect(at('2026-03-01T12:00:00Z')).toBe(asDate('2026-03-01T12:00:00Z'))
  })

  it('changes over at exactly one week', () => {
    expect(at('2026-04-24T12:00:00Z')).toBe(asDate('2026-04-24T12:00:00Z'))
    expect(at('2026-04-24T12:00:01Z')).toBe(relative(-7, 'day'))
  })
})
