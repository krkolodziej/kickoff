import type { PlayerPosition } from '@/api/types'

/**
 * "Goalkeeper", not "GOALKEEPER" — for the places that have room for the whole word.
 *
 * Here rather than beside `PositionBadge` so that the badge module exports components and
 * nothing else, which is what keeps fast refresh working on it.
 */
export function positionLabel(position: PlayerPosition | null): string {
  return position === null ? '—' : position.charAt(0) + position.slice(1).toLowerCase()
}
