import { zodResolver } from '@hookform/resolvers/zod'
import { Trash2, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { ApiError } from '@/api/client'
import { useAddMember, useChangeMemberRole, useMembers, useRemoveMember } from '@/api/organizations'
import { ASSIGNABLE_ROLES, type Membership, type OrganizationRole } from '@/api/types'
import { ErrorState, LoadingState } from '@/components/data/States'
import { RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field } from '@/components/ui/field'
import { FormError } from '@/features/auth/AuthLayout'
import { applyApiErrorToForm } from '@/lib/apiErrorToForm'
import { cn } from '@/lib/cn'

const schema = z.object({
  email: z.email('Enter a valid email address.'),
  role: z.enum(ASSIGNABLE_ROLES),
})

type Values = z.infer<typeof schema>

function MemberRow({
  membership,
  organizationId,
  canManage,
}: {
  membership: Membership
  organizationId: number
  canManage: boolean
}) {
  const changeRole = useChangeMemberRole(organizationId)
  const removeMember = useRemoveMember(organizationId)
  const [rowError, setRowError] = useState<string | null>(null)

  // The owner is the one membership the API refuses to change. Locking the controls says so
  // before the user finds out from a 403 — an affordance, not the rule itself, which lives
  // on the server and would refuse a hand-crafted request just the same.
  const locked = !canManage || membership.role === 'OWNER'

  return (
    <li className="flex flex-wrap items-center gap-3 px-4 py-3">
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium">
          {membership.full_name === '' ? membership.email : membership.full_name}
        </p>
        {membership.full_name === '' ? null : (
          <p className="truncate text-[13px] text-foreground-subtle">{membership.email}</p>
        )}
        {rowError ? (
          <p role="alert" className="mt-1 text-[12.5px] text-danger">
            {rowError}
          </p>
        ) : null}
      </div>

      {locked ? (
        <RoleBadge role={membership.role} />
      ) : (
        <select
          aria-label={`Role of ${membership.email}`}
          value={membership.role}
          disabled={changeRole.isPending}
          onChange={(event) => {
            setRowError(null)
            changeRole.mutate(
              { membershipId: membership.id, role: event.target.value as OrganizationRole },
              {
                onError: (error) =>
                  setRowError(error instanceof ApiError ? error.detail : 'The role was not changed.'),
              },
            )
          }}
          className={cn(
            'h-8 rounded-[var(--radius-control)] border border-border-strong bg-surface px-2 text-[13px]',
            'focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25',
          )}
        >
          {ASSIGNABLE_ROLES.map((role) => (
            <option key={role} value={role}>
              {role.toLowerCase()}
            </option>
          ))}
        </select>
      )}

      <Button
        variant="ghost"
        size="icon"
        aria-label={`Remove ${membership.email}`}
        disabled={locked || removeMember.isPending}
        onClick={() => {
          setRowError(null)
          removeMember.mutate(membership.id, {
            onError: (error) =>
              setRowError(error instanceof ApiError ? error.detail : 'They were not removed.'),
          })
        }}
        className="hover:text-danger"
      >
        <Trash2 className="size-4" />
      </Button>
    </li>
  )
}

export function MembersPanel({
  organizationId,
  canManage,
}: {
  organizationId: number
  canManage: boolean
}) {
  const { data: members, isPending, error, refetch } = useMembers(organizationId)
  const addMember = useAddMember(organizationId)
  const [formError, setFormError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', role: 'MEMBER' },
  })

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)

    try {
      await addMember.mutateAsync(values)
      reset({ email: '', role: values.role })
    } catch (caught) {
      setFormError(applyApiErrorToForm(caught, setError, ['email', 'role']))
    }
  })

  return (
    <section className="flex flex-col gap-4">
      <div className="flex items-end justify-between gap-4 border-b border-border pb-3">
        <div>
          <h2 className="text-lg">Members</h2>
          <p className="mt-0.5 text-[13px] text-foreground-muted">
            Owners and admins may edit the competition. Members can read it.
          </p>
        </div>
      </div>

      {isPending ? <LoadingState label="Loading members" /> : null}

      {error ? (
        <ErrorState
          message={error instanceof ApiError ? error.detail : 'The members could not be loaded.'}
          onRetry={() => void refetch()}
        />
      ) : null}

      {members ? (
        <ul className="surface-panel divide-y divide-border">
          {members.map((membership) => (
            <MemberRow
              key={membership.id}
              membership={membership}
              organizationId={organizationId}
              canManage={canManage}
            />
          ))}
        </ul>
      ) : null}

      {canManage ? (
        <form onSubmit={onSubmit} noValidate className="surface-panel flex flex-col gap-4 p-5">
          <div>
            <h3 className="text-[15px] font-semibold">Add someone</h3>
            <p className="mt-0.5 text-[13px] text-foreground-muted">
              They need an account already — there is no invitation by email yet.
            </p>
          </div>

          <FormError message={formError} />

          <div className="grid gap-4 sm:grid-cols-[1fr_10rem]">
            <Field
              label="Email"
              type="email"
              autoComplete="off"
              placeholder="colleague@example.com"
              error={errors.email?.message}
              {...register('email')}
            />

            <div className="flex flex-col gap-1.5">
              <label
                htmlFor="new-member-role"
                className="text-[13px] font-medium text-foreground-muted"
              >
                Role
              </label>
              <select
                id="new-member-role"
                className="h-10 rounded-[var(--radius-control)] border border-border-strong bg-surface px-3 text-sm focus-visible:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25"
                {...register('role')}
              >
                {ASSIGNABLE_ROLES.map((role) => (
                  <option key={role} value={role}>
                    {role.toLowerCase()}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="flex justify-end">
            <Button type="submit" disabled={isSubmitting}>
              <UserPlus className="size-4" />
              {isSubmitting ? 'Adding…' : 'Add member'}
            </Button>
          </div>
        </form>
      ) : null}
    </section>
  )
}
