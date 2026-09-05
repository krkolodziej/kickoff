import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { z } from 'zod'

import { useCreateLeague, useDeleteLeague, useLeagues } from '@/api/competitions'
import type { League } from '@/api/types'
import { CollectionShell } from '@/components/data/CollectionShell'
import { DataTable, type Column } from '@/components/data/DataTable'
import { Pagination } from '@/components/data/Pagination'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { FormError } from '@/features/auth/AuthLayout'
import { useListParams } from '@/hooks/useListParams'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

const schema = z.object({
  name: z.string().trim().min(2, 'Use at least 2 characters.'),
  description: z.string().trim().max(2000),
})

type Values = z.infer<typeof schema>

function CreateLeagueDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const createLeague = useCreateLeague(organizationId)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { name: '', description: '' } })

  const close = useCallback(() => {
    reset({ name: '', description: '' })
    setFormError(null)
    onClose()
  }, [onClose, reset])

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await createLeague.mutateAsync(values)
      close()
    } catch (caught) {
      setFormError(applyApiErrorToForm(caught, setError, ['name', 'description']))
    }
  })

  return (
    <Dialog
      open={open}
      onClose={close}
      title="New league"
      description="A competition that runs season after season."
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <Field
          label="Name"
          autoFocus
          placeholder="Liga Okręgowa"
          hint="The address is derived from this."
          error={errors.name?.message}
          {...register('name')}
        />

        <Field
          label="Description"
          placeholder="Fourth tier, Podkarpacie"
          error={errors.description?.message}
          {...register('description')}
        />

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Creating…' : 'Create league'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export function LeaguesPanel({
  organizationId,
  canManage,
}: {
  organizationId: number
  canManage: boolean
}) {
  const { params, search, setSearch, setPage, pageSize } = useListParams()
  const { data, isPending, error, refetch } = useLeagues(organizationId, params)
  const deleteLeague = useDeleteLeague(organizationId)
  const [dialogOpen, setDialogOpen] = useState(false)

  const columns: Column<League>[] = [
    {
      key: 'name',
      header: 'League',
      render: (league) => (
        <Link
          to={`/organizations/${organizationId}/leagues/${league.id}`}
          className="block hover:text-primary"
        >
          <p className="font-medium">{league.name}</p>
          <p className="text-[13px] text-foreground-subtle">/{league.slug}</p>
        </Link>
      ),
    },
    {
      key: 'description',
      header: 'Description',
      secondary: true,
      render: (league) => (
        <span className="text-foreground-muted">{league.description || '—'}</span>
      ),
    },
    {
      key: 'seasons',
      header: 'Seasons',
      align: 'right',
      render: (league) => (
        <span className="font-semibold tabular-nums">{league.season_count}</span>
      ),
    },
    {
      // The point of the column: a league row that links only to another list is a step
      // nobody wanted. This one goes where the reader was heading anyway.
      key: 'latest',
      header: 'Latest',
      align: 'right',
      render: (league) =>
        league.latest_season ? (
          <Link
            to={`/organizations/${organizationId}/leagues/${league.id}/seasons/${league.latest_season.id}/overview`}
            className="font-medium hover:text-primary"
          >
            {league.latest_season.name}
          </Link>
        ) : (
          <span className="text-foreground-subtle">—</span>
        ),
    },
  ]

  return (
    <>
      <CollectionShell
        title="Leagues"
        description="Competitions this organization runs."
        searchPlaceholder="Search leagues"
        search={search}
        onSearchChange={setSearch}
        isPending={isPending}
        error={error}
        onRetry={() => void refetch()}
        isEmpty={data?.rows.length === 0}
        emptyTitle="No leagues yet"
        emptyDescription="A league holds the seasons, and a season holds the calendar."
        emptyAction={
          canManage ? <Button onClick={() => setDialogOpen(true)}>Create a league</Button> : null
        }
        action={
          canManage ? (
            <Button size="sm" onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New league
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
          caption="Leagues"
          columns={columns}
          rows={data?.rows ?? []}
          rowKey={(league) => league.id}
          actions={
            canManage
              ? (league) => (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Delete ${league.name}`}
                    onClick={() => deleteLeague.mutate(league.id)}
                    className="hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreateLeagueDialog
        organizationId={organizationId}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </>
  )
}
