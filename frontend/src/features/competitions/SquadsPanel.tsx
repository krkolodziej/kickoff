import { Trash2, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { usePlayers, useTeams } from '@/api/competitions'
import {
  useAddToSquad,
  useRegisterTeam,
  useRemoveFromSquad,
  useRoster,
  useSeasonTeams,
  useUpdateSquadEntry,
  useWithdrawTeam,
  type SeasonPath,
} from '@/api/seasons'
import { PLAYER_POSITIONS, type PlayerPosition, type RosterEntry } from '@/api/types'
import { positionLabel } from '@/lib/positions'
import { EmptyState, LoadingState } from '@/components/data/States'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/cn'

const selectClass =
  'h-9 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px] focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25'

function SquadRow({
  entry,
  path,
  seasonTeamId,
  manageable,
}: {
  entry: RosterEntry
  path: SeasonPath
  seasonTeamId: number
  manageable: boolean
}) {
  const update = useUpdateSquadEntry(path, seasonTeamId)
  const remove = useRemoveFromSquad(path, seasonTeamId)
  const [rowError, setRowError] = useState<string | null>(null)

  const save = (changes: Partial<RosterEntry>) => {
    setRowError(null)
    update.mutate(
      {
        rosterEntryId: entry.id,
        payload: {
          player_id: entry.player_id,
          shirt_number: changes.shirt_number ?? entry.shirt_number,
          position: changes.position === undefined ? entry.position : changes.position,
          captain: changes.captain ?? entry.captain,
        },
      },
      {
        onError: (error) =>
          setRowError(error instanceof ApiError ? error.detail : 'That change was not saved.'),
      },
    )
  }

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-2.5">
      <span className="tabular w-8 shrink-0 text-right text-sm font-semibold text-foreground-muted">
        {entry.shirt_number ?? '–'}
      </span>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">
          {entry.player_name}
          {entry.captain ? (
            <Badge tone="primary" className="ml-2">
              C
            </Badge>
          ) : null}
        </p>
        {rowError ? (
          <p role="alert" className="mt-0.5 text-[12.5px] text-danger">
            {rowError}
          </p>
        ) : null}
      </div>

      {manageable ? (
        <>
          <input
            type="number"
            min={1}
            max={99}
            defaultValue={entry.shirt_number ?? ''}
            aria-label={`Shirt number of ${entry.player_name}`}
            // On blur, not on change: typing "1" on the way to "12" would otherwise send a
            // request for a number somebody else may already wear.
            onBlur={(event) => {
              const raw = event.target.value
              const next = raw === '' ? null : Number(raw)

              if (next !== entry.shirt_number) {
                save({ shirt_number: next })
              }
            }}
            className={cn(selectClass, 'tabular w-16')}
          />

          <select
            value={entry.position ?? ''}
            aria-label={`Position of ${entry.player_name}`}
            onChange={(event) =>
              save({ position: (event.target.value || null) as PlayerPosition | null })
            }
            className={selectClass}
          >
            <option value="">No position</option>
            {PLAYER_POSITIONS.map((position) => (
              <option key={position} value={position}>
                {positionLabel(position)}
              </option>
            ))}
          </select>

          <Button
            variant={entry.captain ? 'primary' : 'outline'}
            size="sm"
            onClick={() => save({ captain: !entry.captain })}
            title="Captain"
          >
            C
          </Button>

          <Button
            variant="ghost"
            size="icon"
            aria-label={`Remove ${entry.player_name} from the squad`}
            onClick={() => remove.mutate(entry.id)}
            className="hover:text-danger"
          >
            <Trash2 className="size-4" />
          </Button>
        </>
      ) : (
        <span className="text-[13px] text-foreground-muted">{positionLabel(entry.position)}</span>
      )}
    </li>
  )
}

function SquadPanel({
  path,
  seasonTeamId,
  teamName,
  manageable,
}: {
  path: SeasonPath
  seasonTeamId: number
  teamName: string
  manageable: boolean
}) {
  const roster = useRoster(path, seasonTeamId)
  const players = usePlayers(path.organizationId)
  const addToSquad = useAddToSquad(path, seasonTeamId)
  const [error, setError] = useState<string | null>(null)

  const inSquad = new Set((roster.data ?? []).map((entry) => entry.player_id))
  const available = (players.data?.rows ?? []).filter((player) => !inSquad.has(player.id))

  return (
    <section className="flex flex-col gap-3">
      <div className="flex items-end justify-between gap-3 border-b border-border pb-3">
        <div>
          <h2 className="text-lg">{teamName}</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {roster.data?.length ?? 0} in the squad
          </p>
        </div>
      </div>

      {roster.isPending ? <LoadingState label="Loading squad" /> : null}

      {roster.data?.length === 0 ? (
        <p className="rounded-[var(--radius-card)] border border-dashed border-border-strong px-4 py-8 text-center text-sm text-foreground-muted">
          Nobody registered yet.
        </p>
      ) : null}

      {roster.data && roster.data.length > 0 ? (
        <ul className="surface-panel divide-y divide-border">
          {roster.data.map((entry) => (
            <SquadRow
              key={entry.id}
              entry={entry}
              path={path}
              seasonTeamId={seasonTeamId}
              manageable={manageable}
            />
          ))}
        </ul>
      ) : null}

      {manageable ? (
        <div className="surface-panel flex flex-col gap-3 p-4">
          {error ? (
            <p role="alert" className="text-[13px] text-danger">
              {error}
            </p>
          ) : null}

          <div className="flex flex-wrap items-center gap-2">
            <select
              id="add-to-squad"
              aria-label="Player to add"
              defaultValue=""
              className={cn(selectClass, 'min-w-48 flex-1')}
              onChange={(event) => {
                const playerId = Number(event.target.value)

                if (!playerId) {
                  return
                }

                setError(null)
                addToSquad.mutate(
                  { player_id: playerId, captain: false },
                  {
                    onError: (caught) =>
                      setError(
                        caught instanceof ApiError ? caught.detail : 'That player was not added.',
                      ),
                  },
                )
                event.target.value = ''
              }}
            >
              <option value="">Add a player…</option>
              {available.map((player) => (
                <option key={player.id} value={player.id}>
                  {player.full_name}
                </option>
              ))}
            </select>

            <span className="inline-flex items-center gap-1.5 text-[13px] text-foreground-subtle">
              <UserPlus className="size-3.5" />
              {available.length} available
            </span>
          </div>
        </div>
      ) : null}
    </section>
  )
}

export function SquadsPanel({ path, manageable }: { path: SeasonPath; manageable: boolean }) {
  const [searchParams, setSearchParams] = useSearchParams()

  const seasonTeams = useSeasonTeams(path)
  const teams = useTeams(path.organizationId)
  const registerTeam = useRegisterTeam(path)
  const withdrawTeam = useWithdrawTeam(path)
  const [registerError, setRegisterError] = useState<string | null>(null)

  // Which club's squad is open lives in the URL, so it survives a refresh and can be linked
  // to — the same rule as the tabs and the search box.
  const selectedId = Number(searchParams.get('club') ?? '') || null
  const selected = seasonTeams.data?.find((row) => row.id === selectedId) ?? seasonTeams.data?.[0]

  const registered = new Set((seasonTeams.data ?? []).map((row) => row.team_id))
  const unregistered = (teams.data?.rows ?? []).filter((team) => !registered.has(team.id))

  return (
    <div className="grid gap-8 lg:grid-cols-[20rem_1fr]">
      <section className="flex flex-col gap-3">
        <div className="border-b border-border pb-3">
          <h2 className="text-lg">Clubs</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            {seasonTeams.data?.length ?? 0} registered for this season.
          </p>
        </div>

        {seasonTeams.isPending ? <LoadingState label="Loading clubs" /> : null}

        {seasonTeams.data?.length === 0 ? (
          <EmptyState
            title="No clubs yet"
            description="Register the clubs taking part before there is anything to schedule."
            action={null}
          />
        ) : null}

        {seasonTeams.data && seasonTeams.data.length > 0 ? (
          <ul className="surface-panel divide-y divide-border">
            {seasonTeams.data.map((row) => (
              <li key={row.id} className="flex items-center">
                <button
                  type="button"
                  onClick={() => setSearchParams({ club: String(row.id) })}
                  aria-current={selected?.id === row.id ? 'true' : undefined}
                  className={cn(
                    'flex min-w-0 flex-1 items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors',
                    selected?.id === row.id
                      ? 'bg-primary-wash font-medium text-primary'
                      : 'hover:bg-surface-muted',
                  )}
                >
                  <span className="min-w-0 flex-1 truncate">{row.team_name}</span>
                  <span className="tabular text-[12px] text-foreground-subtle">
                    {row.squad_size}
                  </span>
                </button>

                {manageable ? (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Withdraw ${row.team_name}`}
                    onClick={() => withdrawTeam.mutate(row.id)}
                    className="mr-2 hover:text-danger"
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                ) : null}
              </li>
            ))}
          </ul>
        ) : null}

        {manageable && unregistered.length > 0 ? (
          <div className="surface-panel flex flex-col gap-2 p-4">
            {registerError ? (
              <p role="alert" className="text-[13px] text-danger">
                {registerError}
              </p>
            ) : null}

            <select
              aria-label="Club to register"
              defaultValue=""
              className={selectClass}
              onChange={(event) => {
                const teamId = Number(event.target.value)

                if (!teamId) {
                  return
                }

                setRegisterError(null)
                registerTeam.mutate(teamId, {
                  onError: (caught) =>
                    setRegisterError(
                      caught instanceof ApiError ? caught.detail : 'That club was not registered.',
                    ),
                })
                event.target.value = ''
              }}
            >
              <option value="">Register a club…</option>
              {unregistered.map((team) => (
                <option key={team.id} value={team.id}>
                  {team.name}
                </option>
              ))}
            </select>
          </div>
        ) : null}
      </section>

      {selected ? (
        <SquadPanel
          path={path}
          seasonTeamId={selected.id}
          teamName={selected.team_name}
          manageable={manageable}
        />
      ) : null}
    </div>
  )
}
