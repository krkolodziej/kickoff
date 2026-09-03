import type { ReactNode } from 'react'

export function PageHeading({
  eyebrow,
  title,
  subtitle,
  actions,
}: {
  eyebrow?: string
  title: string
  subtitle?: string
  actions?: ReactNode
}) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-6">
      <div className="min-w-0">
        {eyebrow ? <p className="text-[13px] font-medium text-primary">{eyebrow}</p> : null}
        <h1 className="mt-1 truncate text-3xl">{title}</h1>
        {subtitle ? <p className="mt-1.5 text-sm text-foreground-muted">{subtitle}</p> : null}
      </div>

      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  )
}
