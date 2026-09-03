import { CalendarPlus, Trash2 } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'

import { ApiError } from '@/api/client'
import {
  useClearFixtures,
  useFixtures,
  useGenerateFixtures,
  useSeasonTeams,
  type SeasonPath,
} from '@/api/seasons'
import type { Fixture } from '@/api/types'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { formatKickOffDay, formatTime } from '@/lib/datetime'
import { cn } from '@/lib/cn'

function GenerateDialog({
  path,
  clubCount,
  open,
  onClose,
}: {
  path: SeasonPath
  clubCount: number
  open: boolean
  onClose: () => void
}) {
  const generate = useGenerateFixtures(path)
  const [doubleRound, setDoubleRound] = useState(true)
  const [firstRoundOn, setFirstRoundOn] = useState('')
  const [daysBetweenRounds, setDaysBetweenRounds] = useState('7')
  const [error, setError] = useState<string | null>(null)

  // Shown before committing, because "132 fixtures" is the sort of number somebody wants to
  // recognise before they click, not discover afterwards.
  const rounds = useMemo(() => {
    const slots = clubCount % 2 === 0 ? clubCount : clubCount + 1
    const single = Math.max(0, slots - 1)

    return doubleRound ? single * 2 : single
  }, [clubCount, doubleRound])

  const fixtures = useMemo(() => {
    const single = (clubCount * (clubCount - 1)) / 2

    return doubleRound ? single * 2 : single
  }, [clubCount, doubleRound])

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Generate the calendar"
      description="Every registered club is paired with every other. It can only be done once — clear the calendar first to redo it."
    >
      <div className="flex flex-col gap-4">
        {error ? (
          <p
            role="alert"
            className="rounded-[var(--radius-control)] border border-danger/30 bg-danger-wash px-3 py-2 text-[13px] text-danger"
          >
            {error}
          </p>
        ) : null}

        <fieldset className="flex flex-col gap-2">
          <legend className="mb-1 text-[13px] font-medium text-foreground-muted">Format</legend>

          {[
            { value: true, label: 'Home and away', hint: 'Everyone plays everyone twice.' },
            { value: false, label: 'Single round', hint: 'Everyone plays everyone once.' },
          ].map((option) => (
            <label
              key={String(option.value)}
              className="flex cursor-pointer items-start gap-2.5 rounded-[var(--radius-control)] border border-border p-3 text-sm has-checked:border-primary has-checked:bg-primary-wash"
            >
              <input
                type="radio"
                name="format"
                checked={doubleRound === option.value}
                onChange={() => setDoubleRound(option.value)}
                className="mt-0.5 accent-[var(--primary)]"
              />
              <span>
                <span className="font-medium">{option.label}</span>
                <span className="block text-[13px] text-foreground-muted">{option.hint}</span>
              </span>
            </label>
          ))}
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="First round"
            type="date"
            hint="Defaults to the season start."
            value={firstRoundOn}
            onChange={(event) => setFirstRoundOn(event.target.value)}
          />
          <Field
            label="Days between rounds"
            type="number"
            min={1}
            max={60}
            value={daysBetweenRounds}
            onChange={(event) => setDaysBetweenRounds(event.target.value)}
          />
        </div>

        <p className="tabular rounded-[var(--radius-control)] bg-surface-muted px-3 py-2 text-[13px] text-foreground-muted">
          {clubCount} clubs → <strong className="text-foreground">{fixtures} fixtures</strong> over{' '}
          {rounds} rounds
        </p>

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button
            disabled={generate.isPending || clubCount < 2}
            onClick={() => {
              setError(null)
              generate.mutate(
                {
                  double_round: doubleRound,
                  first_round_on: firstRoundOn === '' ? null : firstRoundOn,
                  days_between_rounds: Number(daysBetweenRounds) || 7,
                },
                {
                  onSuccess: onClose,
                  onError: (caught) =>
                    setError(
                      caught instanceof ApiError
                        ? caught.detail
                        : 'The calendar was not generated.',
                    ),
                },
              )
            }}
          >
            {generate.isPending ? 'Generating…' : `Generate ${fixtures} fixtures`}
          </Button>
        </div>
      </div>
    </Dialog>
  )
}

function FixtureRow({ fixture, path }: { fixture: Fixture; path: SeasonPath }) {
  const played = fixture.status === 'FINISHED' || fixture.status === 'LIVE'

  return (
    <li>
      <Link
        to={`/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}/fixtures/${fixture.id}`}
        className="flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-surface-muted"
      >
        {/* Home reads towards the middle, away reads away from it — the newspaper
            arrangement, which lets the eye scan a column of results without re-reading the
            names. */}
        <span className="min-w-0 flex-1 truncate text-right font-medium">
          {fixture.home_team_name}
        </span>

        <span
          className={cn(
            'tabular shrink-0 rounded-[var(--radius-control)] px-2 py-0.5 text-[12px]',
            played
              ? 'bg-foreground font-semibold text-background'
              : 'bg-surface-muted text-foreground-muted',
          )}
        >
          {played ? `${fixture.home_score}–${fixture.away_score}` : formatTime(fixture.kick_off_at)}
        </span>

        <span className="min-w-0 flex-1 truncate font-medium">{fixture.away_team_name}</span>

        {fixture.status === 'SCHEDULED' ? null : (
          <MatchStatusBadge status={fixture.status} className="shrink-0" />
        )}
      </Link>
    </li>
  )
}

export function FixturesPanel({
  path,
  manageable,
}: {
  path: SeasonPath
  manageable: boolean
}) {
  const { data: fixtures, isPending, error, refetch } = useFixtures(path)
  const seasonTeams = useSeasonTeams(path)
  const clearFixtures = useClearFixtures(path)
  const [generateOpen, setGenerateOpen] = useState(false)
  const [confirmingClear, setConfirmingClear] = useState(false)

  const byRound = useMemo(() => {
    const grouped = new Map<number, Fixture[]>()

    for (const fixture of fixtures ?? []) {
      const round = grouped.get(fixture.round_number) ?? []
      round.push(fixture)
      grouped.set(fixture.round_number, round)
    }

    return [...grouped.entries()].sort(([a], [b]) => a - b)
  }, [fixtures])

  const clubCount = seasonTeams.data?.length ?? 0

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-border pb-3">
        <div>
          <h2 className="text-lg">Calendar</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {fixtures?.length ?? 0} fixtures over {byRound.length} rounds
          </p>
        </div>

        {manageable && (fixtures?.length ?? 0) > 0 ? (
          <Button
            variant="outline"
            size="sm"
            onClick={() => setConfirmingClear(true)}
            className="hover:border-danger hover:text-danger"
          >
            <Trash2 className="size-3.5" />
            Clear
          </Button>
        ) : null}
      </div>

      {isPending ? <LoadingState label="Loading the calendar" /> : null}

      {error ? (
        <ErrorState
          message={error instanceof ApiError ? error.detail : 'The calendar could not be loaded.'}
          onRetry={() => void refetch()}
        />
      ) : null}

      {fixtures?.length === 0 ? (
        <EmptyState
          title="No calendar yet"
          description={
            clubCount < 2
              ? 'Register at least two clubs first — there is nothing to pair up.'
              : `${clubCount} clubs are registered. One pass pairs every one of them with every other.`
          }
          action={
            manageable && clubCount >= 2 ? (
              <Button onClick={() => setGenerateOpen(true)}>
                <CalendarPlus className="size-4" />
                Generate the calendar
              </Button>
            ) : null
          }
        />
      ) : null}

      {byRound.length > 0 ? (
        <div className="flex flex-col gap-5">
          {byRound.map(([round, roundFixtures]) => (
            <div key={round} className="flex flex-col gap-2">
              <div className="flex items-center gap-2">
                <h3 className="text-[15px] font-semibold">Round {round}</h3>
                {roundFixtures[0].leg === 2 ? <Badge tone="outline">return</Badge> : null}
                <span className="text-[13px] text-foreground-subtle">
                  {formatKickOffDay(roundFixtures[0].kick_off_at)}
                </span>
              </div>

              <ul className="surface-panel divide-y divide-border">
                {roundFixtures.map((fixture) => (
                  <FixtureRow key={fixture.id} fixture={fixture} path={path} />
                ))}
              </ul>
            </div>
          ))}
        </div>
      ) : null}

      <GenerateDialog
        path={path}
        clubCount={clubCount}
        open={generateOpen}
        onClose={() => setGenerateOpen(false)}
      />

      <Dialog
        open={confirmingClear}
        onClose={() => setConfirmingClear(false)}
        title="Clear the calendar?"
        description="Every fixture in this season is removed. Anything recorded against them goes too."
      >
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => setConfirmingClear(false)}>
            Keep it
          </Button>
          <Button
            variant="danger"
            disabled={clearFixtures.isPending}
            onClick={() =>
              clearFixtures.mutate(undefined, { onSuccess: () => setConfirmingClear(false) })
            }
          >
            {clearFixtures.isPending ? 'Clearing…' : 'Clear the calendar'}
          </Button>
        </div>
      </Dialog>
    </section>
  )
}
