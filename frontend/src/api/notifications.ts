import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import type { AppNotification } from './types'

/**
 * How often the bell asks whether anything has happened.
 *
 * Thirty seconds is a compromise with a shelf life: it is short enough that a result recorded
 * on the touchline reaches the office while somebody still cares, and long enough that a
 * browser left open all afternoon does not make two thousand requests. Stage 8 replaces the
 * whole idea with a stream and this constant disappears.
 */
const POLL_INTERVAL_MS = 30_000

export function useUnreadNotificationCount() {
  return useQuery({
    queryKey: qk.unreadNotifications,
    queryFn: () => apiFetch<{ count: number }>('/notifications/unread-count'),
    refetchInterval: POLL_INTERVAL_MS,
    // A tab in the background is not being read, so there is nobody to inform. The interval
    // resumes on focus, and the first thing it does is fetch.
    refetchIntervalInBackground: false,
  })
}

/**
 * The list itself is only fetched when the panel opens — `enabled` rather than a second
 * interval, so a closed bell costs one small request every half minute and nothing else.
 */
export function useNotifications(enabled: boolean) {
  return useQuery({
    queryKey: qk.notifications(),
    queryFn: () => apiFetch<AppNotification[]>('/notifications'),
    enabled,
  })
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => apiFetch<{ marked: number }>('/notifications/read', { method: 'POST' }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: number) =>
      apiFetch<AppNotification>(`/notifications/${id}/read`, { method: 'POST' }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['notifications'] })
    },
  })
}
