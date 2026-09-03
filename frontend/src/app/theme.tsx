import { useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

import { ThemeContext, type ThemePreference } from '@/app/theme-context'

const STORAGE_KEY = 'kickoff.theme'

function readStoredPreference(): ThemePreference {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)

    return stored === 'light' || stored === 'dark' ? stored : 'system'
  } catch {
    // Private windows and blocked site data make this throw rather than return null.
    return 'system'
  }
}

function systemPrefersDark(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [preference, setPreferenceState] = useState<ThemePreference>(readStoredPreference)
  const [systemIsDark, setSystemIsDark] = useState(systemPrefersDark)

  // "system" is resolved here rather than left to a CSS media query, so that the class on
  // <html> is the single source of truth for which theme is showing.
  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const listener = (event: MediaQueryListEvent) => setSystemIsDark(event.matches)

    media.addEventListener('change', listener)

    return () => media.removeEventListener('change', listener)
  }, [])

  const resolved: 'light' | 'dark' =
    preference === 'system' ? (systemIsDark ? 'dark' : 'light') : preference

  useEffect(() => {
    document.documentElement.classList.toggle('dark', resolved === 'dark')
  }, [resolved])

  const setPreference = useCallback((next: ThemePreference) => {
    setPreferenceState(next)

    try {
      if (next === 'system') {
        localStorage.removeItem(STORAGE_KEY)
      } else {
        localStorage.setItem(STORAGE_KEY, next)
      }
    } catch {
      // The toggle still works for this session; it just will not be remembered.
    }
  }, [])

  const value = useMemo(
    () => ({ preference, resolved, setPreference }),
    [preference, resolved, setPreference],
  )

  return <ThemeContext value={value}>{children}</ThemeContext>
}

