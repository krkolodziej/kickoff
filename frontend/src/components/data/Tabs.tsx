import { NavLink } from 'react-router-dom'

import { cn } from '@/lib/cn'

export interface TabDefinition {
  to: string
  label: string
  count?: number
}

/**
 * Real links, not buttons with state.
 *
 * The tab is part of the address, so a tab can be bookmarked, opened in a new window and
 * reached with the back button — and `aria-current` comes from the router rather than from a
 * hand-managed flag.
 */
export function Tabs({ tabs }: { tabs: TabDefinition[] }) {
  return (
    <nav aria-label="Sections" className="-mb-px flex gap-1 overflow-x-auto border-b border-border">
      {tabs.map((tab) => (
        <NavLink
          key={tab.to}
          to={tab.to}
          end
          className={({ isActive }) =>
            cn(
              'flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition-colors',
              isActive
                ? 'border-primary font-medium text-foreground'
                : 'border-transparent text-foreground-muted hover:text-foreground',
            )
          }
        >
          {tab.label}
          {tab.count === undefined ? null : (
            <span className="tabular rounded-full bg-surface-muted px-1.5 py-0.5 text-[11px] text-foreground-subtle">
              {tab.count}
            </span>
          )}
        </NavLink>
      ))}
    </nav>
  )
}
