import { useOutletContext } from 'react-router-dom'

export interface OrganizationContext {
  organizationId: number
  canManage: boolean
}

/**
 * Split out of OrganizationPage.tsx so that file exports nothing but the component. Vite's
 * fast refresh gives up on a module that mixes components with other exports, and silently
 * falls back to reloading the whole page on every edit.
 *
 * Context rather than props, because the sections are rendered by the router through an
 * <Outlet/> and there is nowhere to pass props through.
 */
export function useOrganizationContext(): OrganizationContext {
  return useOutletContext<OrganizationContext>()
}
