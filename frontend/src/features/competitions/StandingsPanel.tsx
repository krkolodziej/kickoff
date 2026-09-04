import { useNavigate } from 'react-router-dom'

import { useStandings, type SeasonPath } from '@/api/seasons'
import type { StandingRow } from '@/api/types'
import { DataTable, type Column } from '@/components/data/DataTable'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'

/**
 * The league table.
 *
 * There is nothing to edit here and no button that writes anything, which is the point: the
 * table is worked out from finished matches every time it is asked for. Record a result and
 * this changes, because it has no separate existence to fall out of step with.
 */
export function StandingsPanel({ path }: { path: SeasonPath }) {
  const standings = useStandings(path)
  const navigate = useNavigate()
  const base = `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}`

  if (standings.isPending) return <LoadingState label="Working out the table" />

  if (standings.isError) {
    return <ErrorState message="The table could not be loaded." onRetry={() => void standings.refetch()} />
  }

  const rows = standings.data

  if (rows.length === 0) {
    return (
      <EmptyState
        title="No clubs in this season yet"
        description="Register the clubs that are taking part and the table will build itself from their results."
        action={<Button onClick={() => void navigate(`${base}/squads`)}>Register clubs</Button>}
      />
    )
  }

  const played = rows.reduce((total, row) => total + row.played, 0)

  return (
    <div className="flex flex-col gap-3">
      <DataTable
        caption="League table"
        rows={rows}
        rowKey={(row) => row.team_id}
        columns={columns}
      />

      <p className="text-[13px] text-foreground-muted">
        {played === 0
          ? 'Nothing has been played yet, so every club starts level.'
          : 'Only finished matches count. A match being played appears here once the whistle goes.'}
      </p>
    </div>
  )
}

/**
 * Six of the ten columns are marked secondary and disappear on a phone. What survives is
 * position, club, played, goal difference and points — which is what somebody checking the
 * table on the touchline actually reads.
 */
const columns: Column<StandingRow>[] = [
  {
    key: 'position',
    header: '#',
    render: (row) => <span className="tabular-nums text-foreground-muted">{row.position}</span>,
  },
  {
    key: 'club',
    header: 'Club',
    render: (row) => <span className="font-medium">{row.team_name}</span>,
  },
  { key: 'played', header: 'P', align: 'right', render: (row) => <Figure value={row.played} /> },
  { key: 'won', header: 'W', align: 'right', secondary: true, render: (row) => <Figure value={row.won} /> },
  { key: 'drawn', header: 'D', align: 'right', secondary: true, render: (row) => <Figure value={row.drawn} /> },
  { key: 'lost', header: 'L', align: 'right', secondary: true, render: (row) => <Figure value={row.lost} /> },
  {
    key: 'goals_for',
    header: 'GF',
    align: 'right',
    secondary: true,
    render: (row) => <Figure value={row.goals_for} />,
  },
  {
    key: 'goals_against',
    header: 'GA',
    align: 'right',
    secondary: true,
    render: (row) => <Figure value={row.goals_against} />,
  },
  {
    key: 'goal_difference',
    header: 'GD',
    align: 'right',
    render: (row) => (
      <span
        className={cn(
          'tabular-nums',
          row.goal_difference > 0 && 'text-primary',
          row.goal_difference < 0 && 'text-foreground-muted',
        )}
      >
        {row.goal_difference > 0 ? `+${row.goal_difference}` : row.goal_difference}
      </span>
    ),
  },
  {
    key: 'points',
    header: 'Pts',
    align: 'right',
    render: (row) => <span className="font-semibold tabular-nums">{row.points}</span>,
  },
]

function Figure({ value }: { value: number }) {
  return <span className="tabular-nums text-foreground-muted">{value}</span>
}
