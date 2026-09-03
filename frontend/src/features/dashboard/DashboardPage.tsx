import { CalendarDays, Trophy, Users } from 'lucide-react'

import { useCurrentUser } from '@/api/auth'

const UPCOMING = [
  {
    Icon: Users,
    title: 'Organizations and members',
    body: 'Workspaces with per-organization roles, so authority is granted where the competition lives rather than globally.',
  },
  {
    Icon: CalendarDays,
    title: 'Squads and calendars',
    body: 'Clubs, seasons, registered squads, and a round robin generated in one deterministic pass.',
  },
  {
    Icon: Trophy,
    title: 'Live matches and standings',
    body: 'Goals and cards recorded minute by minute, with the table and top scorers derived from those events.',
  },
]

export function DashboardPage() {
  const { user } = useCurrentUser()

  return (
    <div className="flex flex-col gap-8">
      <div className="border-b border-border pb-6">
        <p className="text-[13px] font-medium text-primary">Signed in</p>
        <h1 className="mt-1 text-3xl">
          {user?.full_name && user.full_name !== '' ? user.full_name : user?.email}
        </h1>
        <p className="mt-1.5 text-sm text-foreground-muted">{user?.email}</p>
      </div>

      <section className="flex flex-col gap-4">
        <h2 className="text-lg">Coming next</h2>

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {UPCOMING.map(({ Icon, title, body }) => (
            <article key={title} className="surface-panel flex flex-col gap-3 p-5">
              <span className="grid size-9 place-items-center rounded-[var(--radius-control)] bg-primary-wash text-primary">
                <Icon className="size-4.5" strokeWidth={2} />
              </span>
              <h3 className="text-[15px]">{title}</h3>
              <p className="text-[13px] leading-relaxed text-foreground-muted">{body}</p>
            </article>
          ))}
        </div>
      </section>
    </div>
  )
}
