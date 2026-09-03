import { createContext, use } from 'react'

export type ThemePreference = 'light' | 'dark' | 'system'

export interface ThemeContextValue {
  preference: ThemePreference
  resolved: 'light' | 'dark'
  setPreference: (preference: ThemePreference) => void
}

/**
 * Split out of theme.tsx so that file exports nothing but the component. Vite's fast
 * refresh gives up on a module that mixes components with other exports, and silently
 * falls back to full page reloads on every edit.
 */
export const ThemeContext = createContext<ThemeContextValue | null>(null)

export function useTheme(): ThemeContextValue {
  const value = use(ThemeContext)

  if (!value) {
    throw new Error('useTheme must be used inside <ThemeProvider>.')
  }

  return value
}
