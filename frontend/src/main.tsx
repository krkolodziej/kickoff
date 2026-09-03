import '@fontsource-variable/inter'
import '@/styles/index.css'

import { QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { RouterProvider } from 'react-router-dom'

import { setSessionEndedHandler } from '@/api/client'
import { qk } from '@/api/keys'
import { queryClient } from '@/app/query-client'
import { ThemeProvider } from '@/app/theme'
import { router } from '@/routes'

// Fired only when a session that existed has run out. Everything cached belonged to that
// user, so it goes; the session query is then seeded with `null` rather than left empty,
// because an empty cache entry would immediately refetch and start the whole cycle again.
setSessionEndedHandler(() => {
  queryClient.clear()
  queryClient.setQueryData(qk.currentUser, null)
  void router.navigate('/sign-in')
})

createRoot(document.getElementById('root') as HTMLElement).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <RouterProvider router={router} />
      </ThemeProvider>
    </QueryClientProvider>
  </StrictMode>,
)
