import type { ReactNode } from 'react'

import { ApiError } from '@/api/client'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { Pagination } from '@/components/data/Pagination'
import { SearchInput } from '@/components/data/SearchInput'

/**
 * The chrome every collection screen has: a heading, a search box, one of four states, and
 * pagination underneath. Written once so that "loading", "failed", "empty" and "empty
 * because you searched" behave the same everywhere.
 */
export function CollectionShell({
  title,
  description,
  action,
  search,
  onSearchChange,
  searchPlaceholder,
  isPending,
  error,
  onRetry,
  isEmpty,
  emptyTitle,
  emptyDescription,
  emptyAction,
  pagination,
  children,
}: {
  title: string
  description: string
  action?: ReactNode
  search: string
  onSearchChange: (value: string) => void
  searchPlaceholder: string
  isPending: boolean
  error: unknown
  onRetry: () => void
  isEmpty: boolean
  emptyTitle: string
  emptyDescription: string
  emptyAction: ReactNode
  pagination?: ReactNode
  children: ReactNode
}) {
  const searching = search !== ''

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-lg">{title}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">{description}</p>
        </div>
        {action}
      </div>

      <SearchInput value={search} onChange={onSearchChange} placeholder={searchPlaceholder} />

      {isPending ? <LoadingState label={`Loading ${title.toLowerCase()}`} /> : null}

      {error ? (
        <ErrorState
          message={error instanceof ApiError ? error.detail : 'The list could not be loaded.'}
          onRetry={onRetry}
        />
      ) : null}

      {!isPending && !error && isEmpty ? (
        // Two different nothings. "No results for that search" is a dead end you got to on
        // purpose; "nothing here yet" is an invitation.
        searching ? (
          <EmptyState
            title="Nothing matched"
            description={`No ${title.toLowerCase()} match "${search}".`}
            action={
              <button
                type="button"
                onClick={() => onSearchChange('')}
                className="text-sm font-medium text-primary hover:underline"
              >
                Clear the search
              </button>
            }
          />
        ) : (
          <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
        )
      ) : null}

      {!isPending && !error && !isEmpty ? children : null}

      {pagination}
    </section>
  )
}

export { Pagination }
