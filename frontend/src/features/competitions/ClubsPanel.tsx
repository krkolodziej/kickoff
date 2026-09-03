import { zodResolver } from '@hookform/resolvers/zod'
import { Plus, Trash2 } from 'lucide-react'
import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { useCreateTeam, useDeleteTeam, useTeams } from '@/api/competitions'
import type { Team } from '@/api/types'
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
  short_name: z.string().trim().max(32, 'A short name has to fit a table column.'),
})

type Values = z.infer<typeof schema>

function CreateClubDialog({
  organizationId,
  open,
  onClose,
}: {
  organizationId: number
  open: boolean
  onClose: () => void
}) {
  const createTeam = useCreateTeam(organizationId)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { name: '', short_name: '' } })

  const close = useCallback(() => {
    reset({ name: '', short_name: '' })
    setFormError(null)
    onClose()
  }, [onClose, reset])

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await createTeam.mutateAsync(values)
      // Deliberately left open: clubs are entered a dozen at a time, and reopening the
      // dialog for each one turns a five-minute job into a chore.
      reset({ name: '', short_name: '' })
    } catch (caught) {
      setFormError(applyApiErrorToForm(caught, setError, ['name', 'short_name']))
    }
  })

  return (
    <Dialog
      open={open}
      onClose={close}
      title="New club"
      description="Registered once and reused across every season and league."
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <Field
          label="Name"
          autoFocus
          placeholder="Stal Rzeszów"
          error={errors.name?.message}
          {...register('name')}
        />

        <Field
          label="Short name"
          placeholder="Stal"
          hint="What a league table has room to print. Optional."
          error={errors.short_name?.message}
          {...register('short_name')}
        />

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={close}>
            Done
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Adding…' : 'Add club'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export function ClubsPanel({
  organizationId,
  canManage,
}: {
  organizationId: number
  canManage: boolean
}) {
  const { params, search, setSearch, setPage, pageSize } = useListParams()
  const { data, isPending, error, refetch } = useTeams(organizationId, params)
  const deleteTeam = useDeleteTeam(organizationId)
  const [dialogOpen, setDialogOpen] = useState(false)

  const columns: Column<Team>[] = [
    {
      key: 'name',
      header: 'Club',
      render: (team) => (
        <div>
          <p className="font-medium">{team.name}</p>
          <p className="text-[13px] text-foreground-subtle">/{team.slug}</p>
        </div>
      ),
    },
    {
      key: 'short',
      header: 'Short name',
      secondary: true,
      render: (team) => <span className="text-foreground-muted">{team.short_name}</span>,
    },
  ]

  return (
    <>
      <CollectionShell
        title="Clubs"
        description="Every club registered with this organization."
        searchPlaceholder="Search clubs"
        search={search}
        onSearchChange={setSearch}
        isPending={isPending}
        error={error}
        onRetry={() => void refetch()}
        isEmpty={data?.rows.length === 0}
        emptyTitle="No clubs yet"
        emptyDescription="Clubs belong to the organization, not to a league, so they survive promotion and relegation."
        emptyAction={
          canManage ? <Button onClick={() => setDialogOpen(true)}>Add a club</Button> : null
        }
        action={
          canManage ? (
            <Button size="sm" onClick={() => setDialogOpen(true)}>
              <Plus className="size-4" />
              New club
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
          caption="Clubs"
          columns={columns}
          rows={data?.rows ?? []}
          rowKey={(team) => team.id}
          actions={
            canManage
              ? (team) => (
                  <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Delete ${team.name}`}
                    onClick={() => deleteTeam.mutate(team.id)}
                    className="hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )
              : undefined
          }
        />
      </CollectionShell>

      <CreateClubDialog
        organizationId={organizationId}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </>
  )
}
