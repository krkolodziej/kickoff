import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

/**
 * A headline number.
 *
 * Extracted from the season overview once a second and third page wanted the same three
 * tiles. Tabular figures on purpose: a row of numbers that shift sideways as they change is
 * the sort of thing nobody names but everybody notices.
 */
export function Statistic({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="surface-panel px-4 py-3">
      <p className="text-[12px] font-semibold uppercase tracking-wide text-foreground-subtle">
        {label}
      </p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )
}

/**
 * A panel with a title and, optionally, a way out to the full version of what it summarises.
 *
 * The link is optional because not every section is an excerpt — a club's squad is the whole
 * squad, and offering "see all" for a list that is already all of it is a dead end.
 */
export function SectionCard({
  title,
  href,
  linkLabel,
  children,
}: {
  title: string
  href?: string
  linkLabel?: string
  children: ReactNode
}) {
  return (
    <section className="surface-panel flex flex-col px-4 py-3">
      <header className="mb-1 flex items-baseline justify-between gap-3">
        <h2 className="text-[15px] font-semibold">{title}</h2>
        {href && linkLabel ? (
          <Link to={href} className="text-[13px] text-primary hover:underline">
            {linkLabel}
          </Link>
        ) : null}
      </header>

      {children}
    </section>
  )
}

/** What a section says when it has nothing to say. */
export function Nothing({ children }: { children: ReactNode }) {
  return <p className="py-2 text-sm text-foreground-muted">{children}</p>
}
