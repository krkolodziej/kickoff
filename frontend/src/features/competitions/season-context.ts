import { useOutletContext } from 'react-router-dom'

import type { SeasonPath } from '@/api/seasons'

export interface SeasonContext {
  path: SeasonPath
  manageable: boolean
}

/**
 * Split from SeasonPage.tsx so that file exports only its component — Vite's fast refresh
 * gives up on a module mixing components with other exports.
 */
export function useSeasonContext(): SeasonContext {
  return useOutletContext<SeasonContext>()
}
