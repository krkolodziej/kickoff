import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

import {
  useFixtures,
  usePlayerStatistics,
  useStandings,
  type SeasonPath,
} from '@/api/seasons'
import type { Fixture, PlayerStatisticsRow, StandingRow } from '@/api/types'
import { ErrorState, LoadingState } from '@/components/data/States'
import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { formatKickOffDay } from '@/lib/datetime'

/**
 * The season's front page.
 *
 * Every number here is already on another tab, and that is the point of a summary: it answers
 * "where is this season up to" without making anybody open four screens. Nothing is fetched
 * that the other tabs do not fetch either, so opening this first makes the rest instant.
 */
export function OverviewPanel({ path }: { path: SeasonPath }) {
  const standings = useStandings(path)
  const statistics = usePlayerStatistics(path)
  const fixtures = useFixtures(path)

  const base = `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}`

  if (standings.isPending || statistics.isPending || fixtures.isPending) {
    return <LoadingState label="Reading the season" />
  }

  if (standings.isError || statistics.isError || fixtures.isError) {
    return (
      <ErrorState
        message="The season summary could not be loaded."
        onRetry={() => {
          void standings.refetch()
          void statistics.refetch()
          void fixtures.refetch()
        }}
      />
    )
  }

  const table = standings.data
  const scorers = statistics.data
  const calendar = fixtures.data

  const live = calendar.filter((fixture) => fixture.status === 'LIVE')
  const next = calendar
    .filter((fixture) => fixture.status === 'SCHEDULED')
    .slice(0, 5)
  const finished = calendar.filter((fixture) => fixture.status === 'FINISHED').length

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-3">
        <Statistic label="Clubs" value={table.length} />
        <Statistic label="Matches played" value={`${finished} of ${calendar.length}`} />
        <Statistic label="Goals" value={table.reduce((total, row) => total + row.goals_for, 0)} />
      </div>

      {live.length > 0 ? (
        <Card title="Being played now" href={`${base}/fixtures`} linkLabel="Calendar">
          <ul className="divide-y divide-border">
            {live.map((fixture) => (
              <li key={fixture.id}>
                <Link
                  to={`${base}/fixtures/${fixture.id}`}
                  className="flex items-center justify-between gap-3 py-2 text-sm transition-colors hover:text-primary"
                >
                  <span className="truncate">
                    {fixture.home_team_name} — {fixture.away_team_name}
                  </span>
                  <span className="flex shrink-0 items-center gap-2">
                    <span className="font-semibold tabular-nums">
                      {fixture.home_score}:{fixture.away_score}
                    </span>
                    <MatchStatusBadge status={fixture.status} />
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </Card>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-2">
        <Card title="Top of the table" href={`${base}/table`} linkLabel="Full table">
          {table.length === 0 ? (
            <Nothing>No clubs are registered yet.</Nothing>
          ) : (
            <ol className="divide-y divide-border">
              {table.slice(0, 5).map((row) => (
                <TableLine key={row.team_id} row={row} />
              ))}
            </ol>
          )}
        </Card>

        <Card title="Leading scorers" href={`${base}/statistics`} linkLabel="All statistics">
          {scorers.length === 0 ? (
            <Nothing>Nobody has scored yet.</Nothing>
          ) : (
            <ol className="divide-y divide-border">
              {scorers.slice(0, 5).map((row) => (
                <ScorerLine key={row.player_id} row={row} />
              ))}
            </ol>
          )}
        </Card>
      </div>

      <Card title="Coming up" href={`${base}/fixtures`} linkLabel="Calendar">
        {next.length === 0 ? (
          <Nothing>Nothing is scheduled. Generate the calendar to fill the season.</Nothing>
        ) : (
          <ul className="divide-y divide-border">
            {next.map((fixture) => (
              <UpcomingLine key={fixture.id} fixture={fixture} base={base} />
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}

function Statistic({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="surface-panel px-4 py-3">
      <p className="text-[12px] font-semibold uppercase tracking-wide text-foreground-subtle">
        {label}
      </p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )
}

function Card({
  title,
  href,
  linkLabel,
  children,
}: {
  title: string
  href: string
  linkLabel: string
  children: ReactNode
}) {
  return (
    <section className="surface-panel flex flex-col px-4 py-3">
      <header className="mb-1 flex items-baseline justify-between gap-3">
        <h2 className="text-[15px] font-semibold">{title}</h2>
        <Link to={href} className="text-[13px] text-primary hover:underline">
          {linkLabel}
        </Link>
      </header>

      {children}
    </section>
  )
}

function TableLine({ row }: { row: StandingRow }) {
  return (
    <li className="flex items-center gap-3 py-2 text-sm">
      <span className="w-5 shrink-0 tabular-nums text-foreground-subtle">{row.position}</span>
      <span className="min-w-0 flex-1 truncate font-medium">{row.team_name}</span>
      <span className="shrink-0 tabular-nums text-foreground-muted">{row.played}</span>
      <span className="w-8 shrink-0 text-right font-semibold tabular-nums">{row.points}</span>
    </li>
  )
}

function ScorerLine({ row }: { row: PlayerStatisticsRow }) {
  return (
    <li className="flex items-center gap-3 py-2 text-sm">
      <span className="min-w-0 flex-1 truncate">
        <span className="font-medium">
          {row.first_name} {row.last_name}
        </span>
        <span className="ml-2 text-foreground-muted">{row.team_name}</span>
      </span>
      <span className="w-8 shrink-0 text-right font-semibold tabular-nums">{row.goals}</span>
    </li>
  )
}

function UpcomingLine({ fixture, base }: { fixture: Fixture; base: string }) {
  return (
    <li>
      <Link
        to={`${base}/fixtures/${fixture.id}`}
        className="flex items-center justify-between gap-3 py-2 text-sm transition-colors hover:text-primary"
      >
        <span className="min-w-0 truncate">
          {fixture.home_team_name} — {fixture.away_team_name}
        </span>
        <span className="shrink-0 text-[13px] text-foreground-muted">
          {fixture.kick_off_at === null ? `Round ${fixture.round_number}` : formatKickOffDay(fixture.kick_off_at)}
        </span>
      </Link>
    </li>
  )
}

function Nothing({ children }: { children: ReactNode }) {
  return <p className="py-2 text-sm text-foreground-muted">{children}</p>
}
