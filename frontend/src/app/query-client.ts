import { QueryClient } from '@tanstack/react-query'

import { ApiError } from '@/api/client'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,

      // Retrying a 4xx just repeats a request the server has already refused. Retrying a
      // 5xx or a dropped connection is worth one or two attempts.
      retry: (failureCount, error) => {
        if (error instanceof ApiError && error.status < 500) {
          return false
        }

        return failureCount < 2
      },
    },
    mutations: {
      retry: false,
    },
  },
})
