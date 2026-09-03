import { Monitor, Moon, Sun } from 'lucide-react'

import { useTheme, type ThemePreference } from '@/app/theme-context'
import { cn } from '@/lib/cn'

const OPTIONS: { value: ThemePreference; label: string; Icon: typeof Sun }[] = [
  { value: 'light', label: 'Light', Icon: Sun },
  { value: 'system', label: 'Match system', Icon: Monitor },
  { value: 'dark', label: 'Dark', Icon: Moon },
]

/**
 * Three states, not two. A binary toggle cannot express "follow the operating system",
 * which is what most people actually want and what the app defaults to.
 */
export function ThemeToggle() {
  const { preference, setPreference } = useTheme()

  return (
    <div
      role="radiogroup"
      aria-label="Colour theme"
      className="flex items-center gap-0.5 rounded-full border border-border bg-surface-muted p-0.5"
    >
      {OPTIONS.map(({ value, label, Icon }) => {
        const active = preference === value

        return (
          <button
            key={value}
            type="button"
            role="radio"
            aria-checked={active}
            aria-label={label}
            title={label}
            onClick={() => setPreference(value)}
            className={cn(
              'grid size-7 place-items-center rounded-full transition-colors duration-150',
              active
                ? 'bg-surface text-foreground shadow-sm'
                : 'text-foreground-subtle hover:text-foreground',
            )}
          >
            <Icon className="size-3.5" strokeWidth={2} />
          </button>
        )
      })}
    </div>
  )
}
