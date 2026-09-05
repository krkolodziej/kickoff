import { ChevronRight } from 'lucide-react'
import { Outlet, useParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { useOrganization } from '@/api/organizations'
import { useLeague, useSeason, type SeasonPath } from '@/api/seasons'
import { canManage } from '@/api/types'
import { PageHeading } from '@/components/data/PageHeading'
import { ErrorState, LoadingState } from '@/components/data/States'
import { Tabs } from '@/components/data/Tabs'
import type { SeasonContext } from '@/features/competitions/season-context'
import { Link } from 'react-router-dom'

/**
 * The shell: identity, navigation, and the context its sections need. Everything that is
 * *about* the season lives in a section, so adding the table and the statistics in a later
 * stage is a route and a panel rather than another branch in here.
 */
export function SeasonPage() {
  const params = useParams()
  const path: SeasonPath = {
    organizationId: Number(params.organizationId),
    leagueId: Number(params.leagueId),
    seasonId: Number(params.seasonId),
  }

  const { data: organization } = useOrganization(path.organizationId)
  const { data: league } = useLeague(path.organizationId, path.leagueId)
  const { data: season, isPending, error, refetch } = useSeason(path)

  const manageable = organization ? canManage(organization.my_role) : false

  if (isPending) {
    return <LoadingState label="Loading season" />
  }

  if (error) {
    return (
      <ErrorState
        message={error instanceof ApiError ? error.detail : 'The season could not be loaded.'}
        onRetry={() => void refetch()}
      />
    )
  }

  const base = `/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}`

  return (
    <div className="flex flex-col gap-6">
      <div>
        {/*
          * Two segments rather than one unlabelled hop back. A visitor who arrived here from
          * the demonstration button has not seen the organization or the league yet, and a
          * chevron pointing at "Back" tells them nothing about where back is.
          */}
        <nav
          aria-label="Breadcrumb"
          className="mb-4 flex items-center gap-1.5 text-[13px] text-foreground-muted"
        >
          <Link
            to={`/organizations/${path.organizationId}/leagues`}
            className="truncate transition-colors hover:text-foreground"
          >
            {organization?.name ?? 'Organization'}
          </Link>
          <ChevronRight aria-hidden="true" className="size-3.5 shrink-0 text-foreground-subtle" />
          <Link
            to={`/organizations/${path.organizationId}/leagues/${path.leagueId}`}
            className="truncate transition-colors hover:text-foreground"
          >
            {league?.name ?? 'League'}
          </Link>
        </nav>

        <PageHeading
          eyebrow="Season"
          title={season.name}
          subtitle={`${season.start_date}${season.end_date ? ` – ${season.end_date}` : ' – ongoing'}`}
        />
      </div>

      <Tabs
        tabs={[
          { to: `${base}/overview`, label: 'Overview' },
          { to: `${base}/squads`, label: 'Clubs & squads' },
          { to: `${base}/fixtures`, label: 'Calendar' },
          { to: `${base}/table`, label: 'Table' },
          { to: `${base}/statistics`, label: 'Statistics' },
        ]}
      />

      <Outlet context={{ path, manageable } satisfies SeasonContext} />
    </div>
  )
}
