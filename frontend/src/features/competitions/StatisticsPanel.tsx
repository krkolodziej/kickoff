import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import { usePlayerStatistics, type SeasonPath } from '@/api/seasons'
import type { PlayerStatisticsRow } from '@/api/types'
import { DataTable, type Column } from '@/components/data/DataTable'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'

type Ordering = 'goals' | 'cards'

/**
 * Who has done what this season.
 *
 * Counted from match events, including matches still being played — a goal in the second half
 * is a goal, and the scorer list moves while you watch it. The league table does not, because
 * points are awarded at full time. The two disagreeing is correct, not a bug.
 */
export function StatisticsPanel({ path }: { path: SeasonPath }) {
  const statistics = usePlayerStatistics(path)
  const navigate = useNavigate()
  const [ordering, setOrdering] = useState<Ordering>('goals')
  const base = `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}`

  // Sorted here rather than by asking the server again. The whole season's list is already in
  // memory and re-ordering it is a view preference, not a different question — a round trip
  // to change a sort order is a round trip the reader waits for.
  const rows = useMemo(() => {
    const data = statistics.data ?? []

    if (ordering === 'goals') return data

    return [...data].sort(
      (a, b) =>
        b.red_cards - a.red_cards ||
        b.yellow_cards - a.yellow_cards ||
        a.last_name.localeCompare(b.last_name) ||
        a.player_id - b.player_id,
    )
  }, [statistics.data, ordering])

  if (statistics.isPending) return <LoadingState label="Counting" />

  if (statistics.isError) {
    return (
      <ErrorState
        message="The statistics could not be loaded."
        onRetry={() => void statistics.refetch()}
      />
    )
  }

  if (rows.length === 0) {
    return (
      <EmptyState
        title="Nothing has happened yet"
        description="Goals and cards appear here as soon as they are recorded in a match."
        action={<Button onClick={() => void navigate(`${base}/fixtures`)}>Open the calendar</Button>}
      />
    )
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-[13px] text-foreground-muted">
          {rows.length} {rows.length === 1 ? 'player has' : 'players have'} something to show.
        </p>

        <div
          role="group"
          aria-label="Order the list"
          className="flex gap-1 rounded-[var(--radius-control)] bg-surface-muted p-1"
        >
          <OrderButton current={ordering} value="goals" onSelect={setOrdering}>
            Goals
          </OrderButton>
          <OrderButton current={ordering} value="cards" onSelect={setOrdering}>
            Cards
          </OrderButton>
        </div>
      </div>

      <DataTable
        caption="Player statistics"
        rows={rows}
        rowKey={(row) => row.player_id}
        columns={columns}
      />

      <p className="text-[13px] text-foreground-muted">
        Matches being played are counted. Cancelled and postponed ones are not.
      </p>
    </div>
  )
}

function OrderButton({
  current,
  value,
  onSelect,
  children,
}: {
  current: Ordering
  value: Ordering
  onSelect: (ordering: Ordering) => void
  children: string
}) {
  const active = current === value

  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={() => onSelect(value)}
      className={cn(
        'rounded-[calc(var(--radius-control)-2px)] px-3 py-1 text-[13px] font-medium transition-colors',
        active
          ? 'bg-surface text-foreground shadow-sm'
          : 'text-foreground-muted hover:text-foreground',
      )}
    >
      {children}
    </button>
  )
}

const columns: Column<PlayerStatisticsRow>[] = [
  {
    key: 'player',
    header: 'Player',
    render: (row) => (
      <span className="font-medium">
        {row.first_name} {row.last_name}
      </span>
    ),
  },
  {
    key: 'club',
    header: 'Club',
    secondary: true,
    render: (row) => <span className="text-foreground-muted">{row.team_name}</span>,
  },
  {
    key: 'goals',
    header: 'Goals',
    align: 'right',
    render: (row) => <span className="font-semibold tabular-nums">{row.goals}</span>,
  },
  {
    key: 'yellow_cards',
    header: 'Yellow',
    align: 'right',
    // The colours are the cards' own, and they are reserved for exactly this in the design
    // system: nothing else in the application is allowed to be yellow or red.
    render: (row) => <Cards count={row.yellow_cards} className="text-booking" />,
  },
  {
    key: 'red_cards',
    header: 'Red',
    align: 'right',
    render: (row) => <Cards count={row.red_cards} className="text-sending-off" />,
  },
]

function Cards({ count, className }: { count: number; className: string }) {
  if (count === 0) return <span className="tabular-nums text-foreground-subtle">—</span>

  return <span className={cn('font-medium tabular-nums', className)}>{count}</span>
}
