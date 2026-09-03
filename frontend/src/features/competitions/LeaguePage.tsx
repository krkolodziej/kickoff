import { zodResolver } from '@hookform/resolvers/zod'
import { CalendarDays, ChevronLeft, ChevronRight, Plus } from 'lucide-react'
import { useCallback, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useParams } from 'react-router-dom'
import { z } from 'zod'

import { ApiError } from '@/api/client'
import { useOrganization } from '@/api/organizations'
import { useCreateSeason, useLeague, useSeasons } from '@/api/seasons'
import { canManage } from '@/api/types'
import { PageHeading } from '@/components/data/PageHeading'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'
import { Field } from '@/components/ui/field'
import { FormError } from '@/features/auth/AuthLayout'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'

const schema = z.object({
  name: z.string().trim().min(1, 'Name the season.'),
  start_date: z.string().min(1, 'Say when it starts.'),
  end_date: z.string(),
})

type Values = z.infer<typeof schema>

function CreateSeasonDialog({
  organizationId,
  leagueId,
  open,
  onClose,
}: {
  organizationId: number
  leagueId: number
  open: boolean
  onClose: () => void
}) {
  const createSeason = useCreateSeason(organizationId, leagueId)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', start_date: '', end_date: '' },
  })

  const close = useCallback(() => {
    reset({ name: '', start_date: '', end_date: '' })
    setFormError(null)
    onClose()
  }, [onClose, reset])

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await createSeason.mutateAsync({
        ...values,
        end_date: values.end_date === '' ? null : values.end_date,
      })
      close()
    } catch (caught) {
      setFormError(applyApiErrorToForm(caught, setError, ['name', 'start_date', 'end_date']))
    }
  })

  return (
    <Dialog
      open={open}
      onClose={close}
      title="New season"
      description="One edition of this league. Everything that changes year to year hangs off it."
    >
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        <FormError message={formError} />

        <Field
          label="Name"
          autoFocus
          placeholder="2026/27"
          hint="One year, or two consecutive ones."
          error={errors.name?.message}
          {...register('name')}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            label="Starts"
            type="date"
            error={errors.start_date?.message}
            {...register('start_date')}
          />
          <Field
            label="Ends"
            type="date"
            hint="Optional."
            error={errors.end_date?.message}
            {...register('end_date')}
          />
        </div>

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Creating…' : 'Create season'}
          </Button>
        </div>
      </form>
    </Dialog>
  )
}

export function LeaguePage() {
  const params = useParams()
  const organizationId = Number(params.organizationId)
  const leagueId = Number(params.leagueId)

  const { data: organization } = useOrganization(organizationId)
  const { data: league, isPending, error, refetch } = useLeague(organizationId, leagueId)
  const seasons = useSeasons(organizationId, leagueId)
  const [dialogOpen, setDialogOpen] = useState(false)

  const manageable = organization ? canManage(organization.my_role) : false

  if (isPending) {
    return <LoadingState label="Loading league" />
  }

  if (error) {
    return (
      <ErrorState
        message={error instanceof ApiError ? error.detail : 'The league could not be loaded.'}
        onRetry={() => void refetch()}
      />
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link
          to={`/organizations/${organizationId}/leagues`}
          className="mb-4 inline-flex items-center gap-1 text-[13px] text-foreground-muted transition-colors hover:text-foreground"
        >
          <ChevronLeft className="size-3.5" />
          {organization?.name ?? 'Back'}
        </Link>

        <PageHeading
          eyebrow="League"
          title={league.name}
          subtitle={league.description || `/${league.slug}`}
          actions={
            manageable ? (
              <Button size="sm" onClick={() => setDialogOpen(true)}>
                <Plus className="size-4" />
                New season
              </Button>
            ) : null
          }
        />
      </div>

      {seasons.isPending ? <LoadingState label="Loading seasons" /> : null}

      {seasons.data?.length === 0 ? (
        <EmptyState
          title="No seasons yet"
          description="A season is what clubs register for and what a calendar belongs to."
          action={
            manageable ? (
              <Button onClick={() => setDialogOpen(true)}>Create the first season</Button>
            ) : null
          }
        />
      ) : null}

      {seasons.data && seasons.data.length > 0 ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {seasons.data.map((season) => (
            <li key={season.id}>
              <Link
                to={`/organizations/${organizationId}/leagues/${leagueId}/seasons/${season.id}`}
                className="surface-panel group flex items-center gap-4 p-4 transition-[border-color,box-shadow] duration-150 hover:border-border-strong hover:shadow-[var(--shadow-lift)]"
              >
                <span className="grid size-10 shrink-0 place-items-center rounded-[var(--radius-control)] bg-primary-wash text-primary">
                  <CalendarDays className="size-4.5" />
                </span>

                <div className="min-w-0 flex-1">
                  <p className="tabular text-[15px] font-semibold">{season.name}</p>
                  <p className="tabular mt-0.5 text-[13px] text-foreground-subtle">
                    {season.start_date}
                    {season.end_date ? ` – ${season.end_date}` : ' – ongoing'}
                  </p>
                </div>

                <ChevronRight className="size-4 shrink-0 text-foreground-subtle transition-transform duration-150 group-hover:translate-x-0.5" />
              </Link>
            </li>
          ))}
        </ul>
      ) : null}

      <CreateSeasonDialog
        organizationId={organizationId}
        leagueId={leagueId}
        open={dialogOpen}
        onClose={() => setDialogOpen(false)}
      />
    </div>
  )
}
