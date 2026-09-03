import { ChevronLeft, ChevronRight } from 'lucide-react'

import { Button } from '@/components/ui/button'

export function Pagination({
  count,
  page,
  pageSize,
  next,
  previous,
  onChange,
}: {
  count: number
  page: number
  pageSize: number
  next: number | null
  previous: number | null
  onChange: (page: number) => void
}) {
  if (count <= pageSize) {
    return null
  }

  const first = (page - 1) * pageSize + 1
  const last = Math.min(page * pageSize, count)

  return (
    <div className="flex items-center justify-between gap-4 pt-1">
      <p className="tabular text-[13px] text-foreground-muted">
        {first}–{last} of {count}
      </p>

      <div className="flex items-center gap-1.5">
        <Button
          variant="outline"
          size="sm"
          disabled={previous === null}
          onClick={() => previous !== null && onChange(previous)}
        >
          <ChevronLeft className="size-3.5" />
          Previous
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={next === null}
          onClick={() => next !== null && onChange(next)}
        >
          Next
          <ChevronRight className="size-3.5" />
        </Button>
      </div>
    </div>
  )
}
