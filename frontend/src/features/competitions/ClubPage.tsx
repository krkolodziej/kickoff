import { ChevronLeft } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { useTeamProfile } from '@/api/competitions'
import { useOrganization } from '@/api/organizations'
import type { ClubSeasonRow, RosterEntry } from '@/api/types'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeading } from '@/components/data/PageHeading'
import { Nothing, SectionCard, Statistic } from '@/components/data/SectionCard'
import { ErrorState, LoadingState } from '@/components/data/States'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import { Badge } from '@/components/ui/badge'

/**
 * One club, end to end: who is in the squad now and how every season it has entered went.
 *
 * Its own screen rather than a tab, for the reason a league and a match have their own: a
 * club has an identity that outlives any one season, and an address it can be linked to.
 *
 * Everything here arrives in a single request. See the backend's ClubProfile for why — the
 * squad's address is not knowable until the seasons have come back, so assembling this from
 * the existing endpoints would be a waterfall rather than a fetch.
 */
export function ClubPage() {
  const params = useParams()
  const organizationId = Number(params.organizationId)
  const teamId = Number(params.teamId)

  const { data: organization } = useOrganization(organizationId)
  const { data: profile, isPending, error, refetch } = useTeamProfile(organizationId, teamId)

  if (isPending) {
    return <LoadingState label="Loading club" />
  }

  if (error) {
    return (
      <ErrorState
        message={error instanceof ApiError ? error.detail : 'The club could not be loaded.'}
        onRetry={() => void refetch()}
      />
    )
  }

  const { team, squad, seasons } = profile
  const current = seasons[0] ?? null

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link
          to={`/organizations/${organizationId}/clubs`}
          className="mb-4 inline-flex items-center gap-1 text-[13px] text-foreground-muted transition-colors hover:text-foreground"
        >
          <ChevronLeft className="size-3.5" />
          {organization?.name ?? 'Clubs'}
        </Link>

        <PageHeading
          eyebrow="Club"
          title={team.name}
          subtitle={`/${team.slug}`}
          actions={
            current?.position ? (
              <Badge tone="primary">
                {ordinal(current.position)} in {current.season_name}
              </Badge>
            ) : null
          }
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Statistic label="Squad" value={team.squad_size} />
        <Statistic label="Seasons played" value={team.seasons_played} />
        <Statistic label={current ? `Points in ${current.season_name}` : 'Points'} value={current?.points ?? 0} />
      </div>

      <SectionCard
        title={current ? `Squad · ${current.season_name}` : 'Squad'}
        href={
          team.latest_season
            ? `/organizations/${organizationId}/leagues/${team.latest_season.league_id}/seasons/${team.latest_season.id}/squads`
            : undefined
        }
        linkLabel={team.latest_season ? 'Edit squads' : undefined}
      >
        {squad.length === 0 ? (
          <Nothing>
            This club has not been entered for a season yet, so nobody is registered for it.
          </Nothing>
        ) : (
          <ul className="divide-y divide-border">
            {squad.map((entry) => (
              <SquadLine key={entry.id} entry={entry} organizationId={organizationId} />
            ))}
          </ul>
        )}
      </SectionCard>

      {seasons.length === 0 ? null : (
        <div className="flex flex-col gap-2">
          <h2 className="text-[15px] font-semibold">Season by season</h2>
          <DataTable
            caption="Season by season"
            rows={seasons}
            rowKey={(row) => row.season_id}
            columns={seasonColumns(organizationId)}
          />
          <p className="text-[13px] text-foreground-muted">
            Only finished matches count. A league position is worked out from the whole table,
            so it is shown for the season being played.
          </p>
        </div>
      )}
    </div>
  )
}

function SquadLine({ entry, organizationId }: { entry: RosterEntry; organizationId: number }) {
  return (
    <li className="flex items-center gap-3 py-2 text-sm">
      <span className="w-6 shrink-0 text-right tabular-nums text-foreground-subtle">
        {entry.shirt_number ?? '—'}
      </span>
      <Link
        to={`/organizations/${organizationId}/players/${entry.player_id}`}
        className="min-w-0 flex-1 truncate font-medium hover:text-primary"
      >
        {entry.player_name}
      </Link>
      {entry.captain ? <CaptainBadge /> : null}
      <PositionBadge position={entry.position} />
    </li>
  )
}

function seasonColumns(organizationId: number): Column<ClubSeasonRow>[] {
  return [
    {
      key: 'season',
      header: 'Season',
      render: (row) => (
        <Link
          to={`/organizations/${organizationId}/leagues/${row.league_id}/seasons/${row.season_id}/overview`}
          className="block hover:text-primary"
        >
          <p className="font-medium">{row.season_name}</p>
          <p className="text-[13px] text-foreground-subtle">{row.league_name}</p>
        </Link>
      ),
    },
    {
      key: 'position',
      header: 'Pos',
      align: 'right',
      render: (row) =>
        row.position === null ? (
          <span className="tabular-nums text-foreground-subtle">—</span>
        ) : (
          <span className="font-semibold tabular-nums">{row.position}</span>
        ),
    },
    { key: 'played', header: 'P', align: 'right', render: (row) => <Figure value={row.played} /> },
    // Marked secondary in the same places StandingsPanel marks them: a phone has room for
    // the shape of a season, not for every column of it.
    { key: 'won', header: 'W', align: 'right', secondary: true, render: (row) => <Figure value={row.won} /> },
    { key: 'drawn', header: 'D', align: 'right', secondary: true, render: (row) => <Figure value={row.drawn} /> },
    { key: 'lost', header: 'L', align: 'right', secondary: true, render: (row) => <Figure value={row.lost} /> },
    { key: 'goals_for', header: 'GF', align: 'right', secondary: true, render: (row) => <Figure value={row.goals_for} /> },
    { key: 'goals_against', header: 'GA', align: 'right', secondary: true, render: (row) => <Figure value={row.goals_against} /> },
    {
      key: 'points',
      header: 'Pts',
      align: 'right',
      render: (row) => <span className="font-semibold tabular-nums">{row.points}</span>,
    },
  ]
}

function Figure({ value }: { value: number }) {
  return <span className="tabular-nums text-foreground-muted">{value}</span>
}

/**
 * "1st", not "1". A bare number beside a season name reads as a count of something.
 */
function ordinal(position: number): string {
  const rest = position % 100
  const last = position % 10

  if (rest >= 11 && rest <= 13) return `${position}th`
  if (last === 1) return `${position}st`
  if (last === 2) return `${position}nd`
  if (last === 3) return `${position}rd`

  return `${position}th`
}
