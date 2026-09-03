import { LeaguesPanel } from '@/features/competitions/LeaguesPanel'
import { ClubsPanel } from '@/features/competitions/ClubsPanel'
import { PlayersPanel } from '@/features/competitions/PlayersPanel'
import { MembersPanel } from '@/features/organizations/MembersPanel'
import { useOrganizationContext } from '@/features/organizations/organization-context'

/**
 * Thin adapters between the router and the panels.
 *
 * The panels take plain props, so they can be rendered anywhere and tested without a router;
 * these four are the only place that knows the values come from an <Outlet/> context.
 */
export function LeaguesSection() {
  const { organizationId, canManage } = useOrganizationContext()

  return <LeaguesPanel organizationId={organizationId} canManage={canManage} />
}

export function ClubsSection() {
  const { organizationId, canManage } = useOrganizationContext()

  return <ClubsPanel organizationId={organizationId} canManage={canManage} />
}

export function PlayersSection() {
  const { organizationId, canManage } = useOrganizationContext()

  return <PlayersPanel organizationId={organizationId} canManage={canManage} />
}

export function MembersSection() {
  const { organizationId, canManage } = useOrganizationContext()

  return <MembersPanel organizationId={organizationId} canManage={canManage} />
}
