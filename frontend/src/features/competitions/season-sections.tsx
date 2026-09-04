import { FixturesPanel } from '@/features/competitions/FixturesPanel'
import { OverviewPanel } from '@/features/competitions/OverviewPanel'
import { StandingsPanel } from '@/features/competitions/StandingsPanel'
import { StatisticsPanel } from '@/features/competitions/StatisticsPanel'
import { SquadsPanel } from '@/features/competitions/SquadsPanel'
import { useSeasonContext } from '@/features/competitions/season-context'

/**
 * The only place that knows the panels' props arrive through an <Outlet/> context. The panels
 * themselves take plain props, so they render anywhere and test without a router.
 */
export function SquadsSection() {
  const { path, manageable } = useSeasonContext()

  return <SquadsPanel path={path} manageable={manageable} />
}

export function FixturesSection() {
  const { path, manageable } = useSeasonContext()

  return <FixturesPanel path={path} manageable={manageable} />
}

/**
 * Neither of these takes `manageable`: there is nothing on either screen to manage. The table
 * and the statistics are read-only because they are worked out from results rather than
 * stored, so there is no edit for a permission to guard.
 */
export function TableSection() {
  const { path } = useSeasonContext()

  return <StandingsPanel path={path} />
}

export function StatisticsSection() {
  const { path } = useSeasonContext()

  return <StatisticsPanel path={path} />
}

export function OverviewSection() {
  const { path } = useSeasonContext()

  return <OverviewPanel path={path} />
}
