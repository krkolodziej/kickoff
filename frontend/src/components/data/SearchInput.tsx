import { Search } from 'lucide-react'
import { useEffect, useState } from 'react'

/**
 * Debounced, because the value drives both a query and the URL.
 *
 * Without the delay every keystroke would be a request and a history entry, and the browser
 * back button would walk backwards one letter at a time.
 */
export function SearchInput({
  value,
  onChange,
  placeholder,
}: {
  value: string
  onChange: (value: string) => void
  placeholder: string
}) {
  const [draft, setDraft] = useState(value)
  const [lastValue, setLastValue] = useState(value)

  // The URL is the source of truth: when it changes from elsewhere — the back button, or a
  // tab switch that clears the search — the field has to follow. Adjusted during render
  // rather than in an effect, which is React's own recommendation for this: an effect would
  // paint the stale value first and then immediately re-render.
  if (value !== lastValue) {
    setLastValue(value)
    setDraft(value)
  }

  useEffect(() => {
    if (draft === value) {
      return
    }

    const timer = setTimeout(() => onChange(draft), 250)

    return () => clearTimeout(timer)
  }, [draft, value, onChange])

  return (
    <div className="relative">
      <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-foreground-subtle" />
      <input
        type="search"
        value={draft}
        onChange={(event) => setDraft(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="h-9 w-full rounded-[var(--radius-control)] border border-border-strong bg-surface pl-9 pr-3 text-sm placeholder:text-foreground-subtle focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25 sm:w-64"
      />
    </div>
  )
}
