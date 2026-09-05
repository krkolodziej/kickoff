import { ChevronLeft } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { usePlayerProfile } from '@/api/competitions'
import { useOrganization } from '@/api/organizations'
import type { PlayerSeasonRow } from '@/api/types'
import { DataTable, type Column } from '@/components/data/DataTable'
import { PageHeading } from '@/components/data/PageHeading'
import { Nothing, SectionCard, Statistic } from '@/components/data/SectionCard'
import { ErrorState, LoadingState } from '@/components/data/States'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/cn'

/**
 * One person, and what they have done here.
 *
 * The whole reason the player and the squad entry are separate records: a career survives a
 * transfer, so this page can show the same person under two clubs with two numbers, which a
 * position column on the player table never could.
 *
 * There is deliberately no appearances count. Nothing in this application records who took
 * the field, and counting matches-with-an-event would report a defender who never scored as
 * having played nothing — the same argument the season statistics make.
 */
export function PlayerPage() {
  const params = useParams()
  const organizationId = Number(params.organizationId)
  const playerId = Number(params.playerId)

  const { data: organization } = useOrganization(organizationId)
  const { data: profile, isPending, error, refetch } = usePlayerProfile(organizationId, playerId)

  if (isPending) {
    return <LoadingState label="Loading player" />
  }

  if (error) {
    return (
      <ErrorState
        message={error instanceof ApiError ? error.detail : 'The player could not be loaded.'}
        onRetry={() => void refetch()}
      />
    )
  }

  const { player, seasons } = profile
  const squad = player.current_squad

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link
          to={`/organizations/${organizationId}/players`}
          className="mb-4 inline-flex items-center gap-1 text-[13px] text-foreground-muted transition-colors hover:text-foreground"
        >
          <ChevronLeft className="size-3.5" />
          {organization?.name ?? 'Players'}
        </Link>

        <PageHeading
          eyebrow="Player"
          title={player.full_name}
          subtitle={describe(player.age, player.date_of_birth)}
          actions={
            squad ? (
              <>
                {squad.captain ? <CaptainBadge /> : null}
                <PositionBadge position={squad.position} />
                <Link
                  to={`/organizations/${organizationId}/clubs/${squad.team_id}`}
                  className="text-[13px] font-medium hover:text-primary"
                >
                  {squad.team_name}
                  {squad.shirt_number === null ? '' : ` · ${squad.shirt_number}`}
                </Link>
              </>
            ) : (
              <Badge tone="outline">Unattached</Badge>
            )
          }
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <Statistic label="Goals" value={player.goals} />
        <Statistic label="Yellow cards" value={player.yellow_cards} />
        <Statistic label="Red cards" value={player.red_cards} />
      </div>

      {seasons.length === 0 ? (
        <SectionCard title="Career">
          <Nothing>
            Nobody has picked this player for a squad yet. A person is registered with the
            organization first and chosen for a club season by season, which is what lets a
            career survive a transfer.
          </Nothing>
        </SectionCard>
      ) : (
        <div className="flex flex-col gap-2">
          <h2 className="text-[15px] font-semibold">Career</h2>
          <DataTable
            caption="Career"
            rows={seasons}
            // A player can appear twice in one season under two clubs, so the season alone is
            // not a key. The club is what makes the pair unique.
            rowKey={(row) => `${row.season_id}:${row.team_id}`}
            columns={careerColumns(organizationId)}
          />
          <p className="text-[13px] text-foreground-muted">
            Matches being played are counted. Cancelled and postponed ones are not.
          </p>
        </div>
      )}
    </div>
  )
}

/** "Age 27 · born 1998-04-11", and just the age when the date was never filled in. */
function describe(age: number | null, dateOfBirth: string | null): string | undefined {
  if (age === null) return dateOfBirth ?? undefined

  return dateOfBirth === null ? `Age ${age}` : `Age ${age} · born ${dateOfBirth}`
}

function careerColumns(organizationId: number): Column<PlayerSeasonRow>[] {
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
      key: 'club',
      header: 'Club',
      render: (row) => (
        <Link
          to={`/organizations/${organizationId}/clubs/${row.team_id}`}
          className="inline-flex items-center gap-2 text-foreground-muted hover:text-primary"
        >
          {row.team_name}
          {row.captain ? <CaptainBadge /> : null}
        </Link>
      ),
    },
    {
      key: 'shirt',
      header: '#',
      align: 'right',
      secondary: true,
      render: (row) => (
        <span className="tabular-nums text-foreground-muted">{row.shirt_number ?? '—'}</span>
      ),
    },
    {
      key: 'position',
      header: 'Pos',
      secondary: true,
      render: (row) => <PositionBadge position={row.position} />,
    },
    {
      key: 'goals',
      header: 'Goals',
      align: 'right',
      render: (row) => <Count count={row.goals} className="font-semibold text-foreground" />,
    },
    {
      key: 'yellow',
      header: 'Yellow',
      align: 'right',
      // The cards' own colours, reserved for exactly this in the design system — the same
      // treatment the season statistics use, so the two tables read as one thing.
      render: (row) => <Count count={row.yellow_cards} className="text-booking" />,
    },
    {
      key: 'red',
      header: 'Red',
      align: 'right',
      render: (row) => <Count count={row.red_cards} className="text-sending-off" />,
    },
  ]
}

function Count({ count, className }: { count: number; className: string }) {
  if (count === 0) return <span className="tabular-nums text-foreground-subtle">—</span>

  return <span className={cn('font-medium tabular-nums', className)}>{count}</span>
}
