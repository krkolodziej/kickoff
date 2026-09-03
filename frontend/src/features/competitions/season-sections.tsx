import { FixturesPanel } from '@/features/competitions/FixturesPanel'
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
