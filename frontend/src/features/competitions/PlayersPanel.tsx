import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { useCreatePlayer, useDeletePlayer, usePlayers } from '@/api/competitions'
import type { Player } from '@/api/types'
import { CollectionShell } from '@/components/data/CollectionShell'
import { DataTable, type Column } from '@/components/data/DataTable'
import { Pagination } from '@/components/data/Pagination'
import { CaptainBadge, PositionBadge } from '@/components/domain/PositionBadge'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { FormError } from '@/features/auth/AuthLayout'
import { useListParams } from '@/hooks/useListParams'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

const schema = z.object({
  first_name: z.string().trim().min(1, 'Enter a first name.'),
  last_name: z.string().trim().min(1, 'Enter a last name.'),
  date_of_birth: z.string(),
})

type Values = z.infer<typeof schema>

function CreatePlayerDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const createPlayer = useCreatePlayer(organizationId)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { first_name: '', last_name: '', date_of_birth: '' },
  })

  const close = useCallback(() => {
    reset({ first_name: '', last_name: '', date_of_birth: '' })
    setFormError(null)
    onClose()
  }, [onClose, reset])

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await createPlayer.mutateAsync({
        ...values,
        // An empty date input is "not known yet", which the API models as null. Sending ""
        // would be a value, and a wrong one.
        date_of_birth: values.date_of_birth === '' ? null : values.date_of_birth,
      })
      reset({ first_name: '', last_name: '', date_of_birth: '' })
    } catch (caught) {
      setFormError(
        applyApiErrorToForm(caught, setError, ['first_name', 'last_name', 'date_of_birth']),
      )
    }
  })

  return (
    <Dialog
      open={open}
      onClose={close}
      title="New player"
      description="A person, registered with the organization rather than with a club."
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="First name"
            autoFocus
            error={errors.first_name?.message}
            {...register('first_name')}
          />
          <Field label="Last name" error={errors.last_name?.message} {...register('last_name')} />
        </div>

        <Field
          label="Date of birth"
          type="date"
          hint="Optional — it can be filled in later."
          error={errors.date_of_birth?.message}
          {...register('date_of_birth')}
        />

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={close}>
            Done
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Adding…' : 'Add player'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export function PlayersPanel({
  organizationId,
  canManage,
}: {
  organizationId: number
  canManage: boolean
}) {
  const { params, search, setSearch, setPage, pageSize } = useListParams()
  const { data, isPending, error, refetch } = usePlayers(organizationId, params)
  const deletePlayer = useDeletePlayer(organizationId)
  const [dialogOpen, setDialogOpen] = useState(false)

  const columns: Column<Player>[] = [
    {
      key: 'name',
      header: 'Player',
      render: (player) => (
        <Link
          to={`/organizations/${organizationId}/players/${player.id}`}
          className="inline-flex items-center gap-2 font-medium hover:text-primary"
        >
          {player.full_name}
          {player.current_squad?.captain ? <CaptainBadge /> : null}
        </Link>
      ),
    },
    {
      key: 'position',
      header: 'Pos',
      render: (player) => <PositionBadge position={player.current_squad?.position ?? null} />,
    },
    {
      key: 'club',
      header: 'Club',
      secondary: true,
      render: (player) =>
        player.current_squad ? (
          <Link
            to={`/organizations/${organizationId}/clubs/${player.current_squad.team_id}`}
            className="text-foreground-muted hover:text-primary"
          >
            {player.current_squad.team_name}
          </Link>
        ) : (
          <span className="text-foreground-subtle">Unattached</span>
        ),
    },
    {
      key: 'shirt',
      header: '#',
      align: 'right',
      secondary: true,
      render: (player) => (
        <span className="tabular-nums text-foreground-muted">
          {player.current_squad?.shirt_number ?? '—'}
        </span>
      ),
    },
    {
      // Age rather than the date it is derived from: a date of birth is data, an age is what
      // a reader scanning a squad list actually wants. The date is on the player's own page.
      key: 'age',
      header: 'Age',
      align: 'right',
      secondary: true,
      render: (player) => (
        <span className="tabular-nums text-foreground-muted">{player.age ?? '—'}</span>
      ),
    },
    {
      key: 'goals',
      header: 'Goals',
      align: 'right',
      render: (player) =>
        player.goals === 0 ? (
          <span className="tabular-nums text-foreground-subtle">—</span>
        ) : (
          <span className="font-semibold tabular-nums">{player.goals}</span>
        ),
    },
  ]

  return (
    <>
      <CollectionShell
        title="Players"
        description="Registered once, then picked for a squad season by season. Club, number and position come from the current season."
        searchPlaceholder="Search players"
        search={search}
        onSearchChange={setSearch}
        isPending={isPending}
        error={error}
        onRetry={() => void refetch()}
        isEmpty={data?.rows.length === 0}
        emptyTitle="No players yet"
        emptyDescription="Keeping the person separate from the squad entry is what lets a career survive a transfer."
        emptyAction={
          canManage ? <Button onClick={() => setDialogOpen(true)}>Add a player</Button> : null
        }
        action={
          canManage ? (
            <Button size="sm" onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New player
            </Button>
          ) : null
        }
        pagination={
          data ? (
            <Pagination
              count={data.count}
              page={data.page}
              pageSize={pageSize}
              next={data.next}
              previous={data.previous}
              onChange={setPage}
            />
          ) : null
        }
      >
        <DataTable
          caption="Players"
          columns={columns}
          rows={data?.rows ?? []}
          rowKey={(player) => player.id}
          actions={
            canManage
              ? (player) => (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Delete ${player.full_name}`}
                    onClick={() => deletePlayer.mutate(player.id)}
                    className="hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreatePlayerDialog
        organizationId={organizationId}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </>
  )
}
