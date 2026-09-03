import { ChevronLeft, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, Outlet, useNavigate, useParams } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { useDeleteOrganization, useOrganization } from '@/api/organizations'
import { canManage } from '@/api/types'
import type { OrganizationContext } from '@/features/organizations/organization-context'
import { PageHeading } from '@/components/data/PageHeading'
import { ErrorState, LoadingState } from '@/components/data/States'
import { Tabs } from '@/components/data/Tabs'
import { RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog } from '@/components/ui/dialog'

export function OrganizationPage() {
  const { organizationId: rawId } = useParams()
  const organizationId = Number(rawId)
  const navigate = useNavigate()

  const { data: organization, isPending, error, refetch } = useOrganization(organizationId)
  const deleteOrganization = useDeleteOrganization(organizationId)
  const [confirmingDelete, setConfirmingDelete] = useState(false)

  if (isPending) {
    return <LoadingState label="Loading organization" />
  }

  if (error) {
    const notFound = error instanceof ApiError && error.status === 404

    return (
      <ErrorState
        // A 404 here means either that the organization does not exist or that the reader is
        // not in it — and the server deliberately declines to say which. The message has to
        // be equally true of both, or it gives away what the status code withheld.
        message={
          notFound
            ? 'This organization is not available to you.'
            : ((error as ApiError).detail ?? 'The organization could not be loaded.')
        }
        onRetry={notFound ? undefined : () => void refetch()}
      />
    )
  }

  const manageable = canManage(organization.my_role)
  const base = `/organizations/${organizationId}`

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link
          to="/dashboard"
          className="mb-4 inline-flex items-center gap-1 text-[13px] text-foreground-muted transition-colors hover:text-foreground"
        >
          <ChevronLeft className="size-3.5" />
          All organizations
        </Link>

        <PageHeading
          title={organization.name}
          subtitle={`/${organization.slug}`}
          actions={
            <>
              <RoleBadge role={organization.my_role} />
              {organization.my_role === 'OWNER' ? (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setConfirmingDelete(true)}
                  className="hover:border-danger hover:text-danger"
                >
                  <Trash2 className="size-3.5" />
                  Delete
                </Button>
              ) : null}
            </>
          }
        />
      </div>

      <Tabs
        tabs={[
          { to: `${base}/leagues`, label: 'Leagues' },
          { to: `${base}/clubs`, label: 'Clubs' },
          { to: `${base}/players`, label: 'Players' },
          { to: `${base}/members`, label: 'Members', count: organization.member_count },
        ]}
      />

      <Outlet context={{ organizationId, canManage: manageable } satisfies OrganizationContext} />

      <Dialog
        open={confirmingDelete}
        onClose={() => setConfirmingDelete(false)}
        title={`Delete ${organization.name}?`}
        description="Every league, club, player and match inside it goes too. This cannot be undone."
      >
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => setConfirmingDelete(false)}>
            Keep it
          </Button>
          <Button
            variant="danger"
            disabled={deleteOrganization.isPending}
            onClick={() =>
              deleteOrganization.mutate(undefined, {
                onSuccess: () => {
                  setConfirmingDelete(false)
                  void navigate('/dashboard', { replace: true })
                },
              })
            }
          >
            {deleteOrganization.isPending ? 'Deleting…' : 'Delete organization'}
          </Button>
        </div>
      </Dialog>
    </div>
  )
}
