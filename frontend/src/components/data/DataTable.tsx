import type { ReactNode } from 'react'

import { cn } from '@/lib/cn'

export interface Column<T> {
  key: string
  header: string
  render: (row: T) => ReactNode
  /** Hidden below the `sm` breakpoint. Use for anything a phone can live without. */
  secondary?: boolean
  align?: 'left' | 'right'
}

export function DataTable<T>({
  columns,
  rows,
  rowKey,
  actions,
  caption,
}: {
  columns: Column<T>[]
  rows: T[]
  rowKey: (row: T) => number | string
  actions?: (row: T) => ReactNode
  caption: string
}) {
  return (
    <div className="surface-panel overflow-x-auto">
      <table className="w-full text-sm">
        <caption className="sr-only">{caption}</caption>

        <thead>
          <tr className="border-b border-border text-left">
            {columns.map((column) => (
              <th
                key={column.key}
                scope="col"
                className={cn(
                  'px-4 py-2.5 text-[12px] font-semibold uppercase tracking-wide text-foreground-subtle',
                  column.secondary && 'hidden sm:table-cell',
                  column.align === 'right' && 'text-right',
                )}
              >
                {column.header}
              </th>
            ))}
            {actions ? <th scope="col" className="w-12 px-4 py-2.5" /> : null}
          </tr>
        </thead>

        <tbody className="divide-y divide-border">
          {rows.map((row) => (
            <tr key={rowKey(row)} className="transition-colors hover:bg-surface-muted">
              {columns.map((column) => (
                <td
                  key={column.key}
                  className={cn(
                    'px-4 py-3',
                    column.secondary && 'hidden sm:table-cell',
                    column.align === 'right' && 'text-right',
                  )}
                >
                  {column.render(row)}
                </td>
              ))}
              {actions ? <td className="px-4 py-3 text-right">{actions(row)}</td> : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
