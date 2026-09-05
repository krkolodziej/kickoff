import { ChevronLeft } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { useLiveMatch, useMatchTransition, useRecordEvent } from '@/api/match'
import type { Transport } from '@/api/realtime'
import { useOrganization } from '@/api/organizations'
import { useSquadOf, type SeasonPath } from '@/api/seasons'
import {
  canManage,
  MATCH_EVENT_TYPES,
  type Fixture,
  type MatchEventType,
  type MatchStatus,
} from '@/api/types'
import { MatchStatusBadge } from '@/components/domain/MatchStatusBadge'
import { MatchTimeline } from '@/components/domain/MatchTimeline'
import { ErrorState, LoadingState } from '@/components/data/States'
import { Button } from '@/components/ui/button'
import { formatKickOff } from '@/lib/datetime'
import { cn } from '@/lib/cn'

const controlClass =
  'h-9 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

const TRANSITION_LABEL: Record<MatchStatus, string> = {
  LIVE: 'Kick off',
  FINISHED: 'Full time',
  CANCELLED: 'Cancel',
  POSTPONED: 'Postpone',
  SCHEDULED: 'Back to the calendar',
}

const EVENT_LABEL: Record<MatchEventType, string> = {
  GOAL: 'Goal',
  YELLOW_CARD: 'Yellow card',
  RED_CARD: 'Red card',
  SUBSTITUTION: 'Substitution',
}

function Scoreboard({ fixture }: { fixture: Fixture }) {
  const played = fixture.status === 'FINISHED' || fixture.status === 'LIVE'

  return (
    <div className="surface-panel grid grid-cols-[1fr_auto_1fr] items-center gap-4 p-6">
      <p className="truncate text-right text-lg font-semibold">{fixture.home_team_name}</p>

      <p className="tabular whitespace-nowrap text-4xl font-bold sm:text-5xl">
        {played ? (
          <>
            {fixture.home_score}
            <span className="mx-2 text-foreground-subtle">:</span>
            {fixture.away_score}
          </>
        ) : (
          <span className="text-foreground-subtle">–</span>
        )}
      </p>

      <p className="truncate text-lg font-semibold">{fixture.away_team_name}</p>
    </div>
  )
}

function EventForm({
  path,
  fixture,
}: {
  path: SeasonPath
  fixture: Fixture
}) {
  const [type, setType] = useState<MatchEventType>('GOAL')
  const [teamId, setTeamId] = useState(fixture.home_team_id)
  const [minute, setMinute] = useState('1')
  const [playerId, setPlayerId] = useState('')
  const [relatedPlayerId, setRelatedPlayerId] = useState('')
  const [error, setError] = useState<string | null>(null)

  // Only the squad of the club being credited. Offering the whole organization would let an
  // operator pick somebody the server is about to refuse.
  const { players } = useSquadOf(path, teamId)
  const recordEvent = useRecordEvent(path, fixture.id)

  const needsSecondPlayer = type === 'SUBSTITUTION'

  return (
    <form
      className="surface-panel flex flex-col gap-3 p-4"
      onSubmit={(event) => {
        event.preventDefault()
        setError(null)

        recordEvent.mutate(
          {
            type,
            minute: Number(minute) || 1,
            team_id: teamId,
            player_id: Number(playerId),
            related_player_id: needsSecondPlayer ? Number(relatedPlayerId) || null : null,
          },
          {
            onSuccess: () => {
              setPlayerId('')
              setRelatedPlayerId('')
            },
            onError: (caught) =>
              setError(caught instanceof ApiError ? caught.detail : 'That was not recorded.'),
          },
        )
      }}
    >
      <h3 className="text-[15px] font-semibold">Record what happened</h3>

      {error ? (
        <p role="alert" className="text-[13px] text-danger">
          {error}
        </p>
      ) : null}

      <div className="flex flex-wrap gap-2">
        <select
          aria-label="Club"
          value={teamId}
          onChange={(event) => {
            setTeamId(Number(event.target.value))
            setPlayerId('')
            setRelatedPlayerId('')
          }}
          className={cn(controlClass, 'min-w-36 flex-1')}
        >
          <option value={fixture.home_team_id}>{fixture.home_team_name}</option>
          <option value={fixture.away_team_id}>{fixture.away_team_name}</option>
        </select>

        <select
          aria-label="What happened"
          value={type}
          onChange={(event) => setType(event.target.value as MatchEventType)}
          className={controlClass}
        >
          {MATCH_EVENT_TYPES.map((option) => (
            <option key={option} value={option}>
              {EVENT_LABEL[option]}
            </option>
          ))}
        </select>

        <input
          type="number"
          min={1}
          max={180}
          aria-label="Minute"
          value={minute}
          onChange={(event) => setMinute(event.target.value)}
          className={cn(controlClass, 'tabular w-16')}
        />
      </div>

      <div className="flex flex-wrap gap-2">
        <select
          aria-label={needsSecondPlayer ? 'Player coming off' : 'Player'}
          required
          value={playerId}
          onChange={(event) => setPlayerId(event.target.value)}
          className={cn(controlClass, 'min-w-40 flex-1')}
        >
          <option value="">{needsSecondPlayer ? 'Coming off…' : 'Player…'}</option>
          {players.map((entry) => (
            <option key={entry.id} value={entry.player_id}>
              {entry.shirt_number === null ? '' : `${entry.shirt_number}. `}
              {entry.player_name}
            </option>
          ))}
        </select>

        {needsSecondPlayer ? (
          <select
            aria-label="Player coming on"
            required
            value={relatedPlayerId}
            onChange={(event) => setRelatedPlayerId(event.target.value)}
            className={cn(controlClass, 'min-w-40 flex-1')}
          >
            <option value="">Coming on…</option>
            {players
              .filter((entry) => String(entry.player_id) !== playerId)
              .map((entry) => (
                <option key={entry.id} value={entry.player_id}>
                  {entry.shirt_number === null ? '' : `${entry.shirt_number}. `}
                  {entry.player_name}
                </option>
              ))}
          </select>
        ) : null}

        <Button type="submit" disabled={recordEvent.isPending || playerId === ''}>
          {recordEvent.isPending ? 'Recording…' : 'Record'}
        </Button>
      </div>
    </form>
  )
}

/**
 * Says that the page is keeping itself up to date, and how.
 *
 * The "how" is a tooltip rather than a label because it is not the reader's problem: whether
 * updates arrive over a stream or on a three-second timer, the screen behaves the same. It is
 * worth being able to see, though — when somebody reports that a match "stopped updating",
 * the first useful question is which transport they were on.
 */
function LiveIndicator({ transport }: { transport: Transport }) {
  return (
    <span
      className="inline-flex items-center gap-1.5 text-[12px] font-medium text-danger"
      title={transport === 'stream' ? 'Updating over a live stream' : 'Updating every few seconds'}
    >
      <span className="relative flex size-2">
        {transport === 'stream' ? (
          <span className="absolute inline-flex size-full animate-ping rounded-full bg-danger opacity-60" />
        ) : null}
        <span className="relative inline-flex size-2 rounded-full bg-danger" />
      </span>
      Live
    </span>
  )
}

export function MatchPage() {
  const params = useParams()
  const path: SeasonPath = {
    organizationId: Number(params.organizationId),
    leagueId: Number(params.leagueId),
    seasonId: Number(params.seasonId),
  }
  const fixtureId = Number(params.fixtureId)

  const { data: organization } = useOrganization(path.organizationId)
  const { match, events, live, transport } = useLiveMatch(path, fixtureId)
  const { data: fixture, isPending, error, refetch } = match
  const transition = useMatchTransition(path, fixtureId)
  const [transitionError, setTransitionError] = useState<string | null>(null)

  const manageable = organization ? canManage(organization.my_role) : false

  if (isPending) {
    return <LoadingState label="Loading match" />
  }

  if (error) {
    return (
      <ErrorState
        message={error instanceof ApiError ? error.detail : 'The match could not be loaded.'}
        onRetry={() => void refetch()}
      />
    )
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-6">
      <div>
        <Link
          to={`/organizations/${path.organizationId}/leagues/${path.leagueId}/seasons/${path.seasonId}/fixtures`}
          className="mb-4 inline-flex items-center gap-1 text-[13px] text-foreground-muted transition-colors hover:text-foreground"
        >
          <ChevronLeft className="size-3.5" />
          Calendar
        </Link>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <MatchStatusBadge status={fixture.status} />

            {live ? <LiveIndicator transport={transport} /> : null}
            <span className="text-[13px] text-foreground-muted">
              Round {fixture.round_number} · {formatKickOff(fixture.kick_off_at)}
            </span>
          </div>

          {live ? (
            <span className="text-[12px] text-foreground-subtle">Updating automatically</span>
          ) : null}
        </div>
      </div>

      <Scoreboard fixture={fixture} />

      {manageable ? (
        <div className="flex flex-col gap-2">
          <div className="flex flex-wrap gap-2">
            {/* Only the transitions the server would accept. The list comes from the
                response, so the buttons cannot drift out of step with the machine. */}
            {fixture.allowed_transitions.map((target) => (
              <Button
                key={target}
                variant={target === 'LIVE' || target === 'FINISHED' ? 'primary' : 'outline'}
                size="sm"
                disabled={transition.isPending}
                onClick={() => {
                  setTransitionError(null)
                  transition.mutate(target, {
                    onError: (caught) =>
                      setTransitionError(
                        caught instanceof ApiError ? caught.detail : 'That did not work.',
                      ),
                  })
                }}
              >
                {TRANSITION_LABEL[target]}
              </Button>
            ))}

            {fixture.allowed_transitions.length === 0 ? (
              <p className="text-[13px] text-foreground-muted">
                This match is over. Its record is corrected by adding events, never by
                reopening it.
              </p>
            ) : null}
          </div>

          {transitionError ? (
            <p role="alert" className="text-[13px] text-danger">
              {transitionError}
            </p>
          ) : null}
        </div>
      ) : null}

      <section className="flex flex-col gap-3">
        <h2 className="border-b border-border pb-2 text-lg">Timeline</h2>

        {events.isPending ? (
          <LoadingState label="Loading events" />
        ) : (
          <MatchTimeline events={events.data ?? []} />
        )}
      </section>

      {manageable && live ? <EventForm path={path} fixture={fixture} /> : null}
    </div>
  )
}
