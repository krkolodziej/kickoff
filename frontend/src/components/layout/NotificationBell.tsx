import { Bell, CalendarClock, Trophy } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'

import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotifications,
  useUnreadNotificationCount,
} from '@/api/notifications'
import type { AppNotification } from '@/api/types'
import { formatRelative } from '@/lib/datetime'
import { cn } from '@/lib/cn'

/**
 * The bell.
 *
 * Two queries with different rhythms, which is the whole design: a small count on a timer, and
 * the list only when somebody actually looks. Polling the list instead would drag thirty rows
 * across the wire every half minute to render one number nobody had asked to see.
 */
export function NotificationBell() {
  const [open, setOpen] = useState(false)
  const panel = useRef<HTMLDivElement>(null)

  const unread = useUnreadNotificationCount()
  const notifications = useNotifications(open)
  const markAllRead = useMarkAllNotificationsRead()

  // Closing on an outside click and on Escape, because a panel that can only be dismissed by
  // clicking the same button again is a panel people leave open by accident.
  useEffect(() => {
    if (!open) return

    const onPointerDown = (event: PointerEvent) => {
      if (!panel.current?.contains(event.target as Node)) setOpen(false)
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('pointerdown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('pointerdown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  const count = unread.data?.count ?? 0
  const rows = notifications.data ?? []

  return (
    <div className="relative" ref={panel}>
      <button
        type="button"
        onClick={() => setOpen((wasOpen) => !wasOpen)}
        aria-expanded={open}
        aria-haspopup="dialog"
        aria-label={count === 0 ? 'Notifications' : `Notifications, ${count} unread`}
        className="relative grid size-8 place-items-center rounded-[var(--radius-control)] text-foreground-muted transition-colors hover:bg-surface-muted hover:text-foreground"
      >
        <Bell className="size-4" />

        {count > 0 ? (
          <span
            aria-hidden="true"
            className="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-semibold leading-4 text-primary-foreground"
          >
            {count > 9 ? '9+' : count}
          </span>
        ) : null}
      </button>

      {open ? (
        <div
          role="dialog"
          aria-label="Notifications"
          className="surface-panel absolute right-0 top-10 z-50 w-[min(22rem,calc(100vw-2rem))] overflow-hidden p-0 shadow-lg"
        >
          <header className="flex items-center justify-between gap-3 border-b border-border px-4 py-2.5">
            <h2 className="text-[13px] font-semibold">Notifications</h2>

            {count > 0 ? (
              <button
                type="button"
                onClick={() => markAllRead.mutate()}
                disabled={markAllRead.isPending}
                className="text-[12px] text-primary hover:underline disabled:opacity-50"
              >
                Mark all read
              </button>
            ) : null}
          </header>

          <div className="max-h-[min(26rem,60vh)] overflow-y-auto">
            {notifications.isPending ? (
              <p className="px-4 py-6 text-center text-[13px] text-foreground-muted">Loading…</p>
            ) : rows.length === 0 ? (
              <p className="px-4 py-6 text-center text-[13px] text-foreground-muted">
                Nothing yet. Results and reminders show up here.
              </p>
            ) : (
              <ul className="divide-y divide-border">
                {rows.map((row) => (
                  <Row key={row.id} notification={row} onNavigate={() => setOpen(false)} />
                ))}
              </ul>
            )}
          </div>
        </div>
      ) : null}
    </div>
  )
}

function Row({
  notification,
  onNavigate,
}: {
  notification: AppNotification
  onNavigate: () => void
}) {
  const navigate = useNavigate()
  const markRead = useMarkNotificationRead()
  const unread = notification.read_at === null

  const Icon = notification.type === 'MATCH_FINISHED' ? Trophy : CalendarClock

  return (
    <li>
      <button
        type="button"
        onClick={() => {
          if (unread) markRead.mutate(notification.id)
          onNavigate()
          void navigate(notification.link)
        }}
        className={cn(
          'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-muted',
          unread && 'bg-primary-wash/40',
        )}
      >
        <Icon
          className={cn('mt-0.5 size-4 shrink-0', unread ? 'text-primary' : 'text-foreground-subtle')}
        />

        <span className="min-w-0 flex-1">
          <span className="flex items-baseline justify-between gap-2">
            <span className={cn('truncate text-[13px]', unread ? 'font-semibold' : 'font-medium')}>
              {notification.title}
            </span>
            <span className="shrink-0 text-[11px] text-foreground-subtle">
              {formatRelative(notification.created_at)}
            </span>
          </span>

          <span className="mt-0.5 block text-[12px] text-foreground-muted">{notification.body}</span>
          <span className="mt-1 block text-[11px] text-foreground-subtle">
            {notification.organization_name}
          </span>
        </span>
      </button>
    </li>
  )
}
