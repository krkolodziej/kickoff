import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'

import type { ListParams } from '@/api/types'

const PAGE_SIZE = 10

/**
 * List state lives in the URL, not in component state.
 *
 * It costs nothing and buys a great deal: a filtered, paged view can be linked to and
 * bookmarked, the back button walks through it, and a refresh does not silently reset
 * someone to page one of an unfiltered list.
 *
 * Changing the search resets the page, because page 4 of a narrower result set is usually
 * empty — and an empty table looks like "no such club" rather than "wrong page".
 */
export function useListParams(): {
  params: ListParams
  search: string
  setSearch: (value: string) => void
  setPage: (page: number) => void
  pageSize: number
} {
  const [searchParams, setSearchParams] = useSearchParams()

  const search = searchParams.get('q') ?? ''
  const page = Number(searchParams.get('page') ?? '1')

  const setSearch = useCallback(
    (value: string) => {
      setSearchParams((current) => {
        const next = new URLSearchParams(current)

        if (value === '') {
          next.delete('q')
        } else {
          next.set('q', value)
        }

        next.delete('page')

        return next
      })
    },
    [setSearchParams],
  )

  const setPage = useCallback(
    (value: number) => {
      setSearchParams((current) => {
        const next = new URLSearchParams(current)

        if (value <= 1) {
          next.delete('page')
        } else {
          next.set('page', String(value))
        }

        return next
      })
    },
    [setSearchParams],
  )

  const params = useMemo<ListParams>(
    () => ({
      search: search === '' ? undefined : search,
      page: Number.isFinite(page) && page > 1 ? page : 1,
      pageSize: PAGE_SIZE,
    }),
    [search, page],
  )

  return { params, search, setSearch, setPage, pageSize: PAGE_SIZE }
}
