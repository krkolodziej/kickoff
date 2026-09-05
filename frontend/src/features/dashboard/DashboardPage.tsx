import { ChevronRight, Plus, Users } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'

import { ApiError } from '@/api/client'
import { useOrganizations } from '@/api/organizations'
import type { Organization } from '@/api/types'
import { EmptyState, ErrorState, LoadingState } from '@/components/data/States'
import { PageHeading } from '@/components/data/PageHeading'
import { Badge, RoleBadge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { CreateOrganizationDialog } from '@/features/organizations/CreateOrganizationDialog'

/**
 * A deterministic mark, so the same organization always looks the same without anyone
 * having to upload a crest. The hue comes from the name; lightness and chroma stay fixed,
 * which keeps every mark equally readable in both themes.
 */
function OrganizationMark({ name }: { name: string }) {
  let hash = 0

  for (let index = 0; index < name.length; index += 1) {
    hash = (hash * 31 + name.charCodeAt(index)) % 360
  }

  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase()

  return (
    <span
      aria-hidden="true"
      className="grid size-10 shrink-0 place-items-center rounded-[var(--radius-control)] text-[13px] font-bold text-white"
      style={{ backgroundColor: `oklch(0.55 0.13 ${hash})` }}
    >
      {initials}
    </span>
  )
}

function plural(count: number, noun: string): string {
  return `${count} ${noun}${count === 1 ? '' : 's'}`
}

function OrganizationCard({ organization }: { organization: Organization }) {
  return (
    <Link
      to={`/organizations/${organization.id}`}
      className="surface-panel group flex items-center gap-4 p-4 transition-[border-color,box-shadow] duration-150 hover:border-border-strong hover:shadow-[var(--shadow-lift)]"
    >
      <OrganizationMark name={organization.name} />

      <div className="min-w-0 flex-1">
        <p className="truncate text-[15px] font-semibold">{organization.name}</p>
        {/*
          * What is inside, on one muted line rather than as three more badges. The right-hand
          * side already carries two, and a card with five is a card nobody reads.
          */}
        <p className="mt-0.5 truncate text-[13px] text-foreground-subtle">
          {plural(organization.league_count, 'league')} · {plural(organization.team_count, 'club')}{' '}
          · {plural(organization.player_count, 'player')}
        </p>
      </div>

      <div className="flex shrink-0 items-center gap-3">
        <Badge tone="outline">
          <Users className="mr-1 size-3" />
          {organization.member_count}
        </Badge>
        <RoleBadge role={organization.my_role} />
        <ChevronRight className="size-4 text-foreground-subtle transition-transform duration-150 group-hover:translate-x-0.5" />
      </div>
    </Link>
  )
}

export function DashboardPage() {
  const [dialogOpen, setDialogOpen] = useState(false)
  const { data: organizations, isPending, error, refetch } = useOrganizations()

  return (
    <div className="flex flex-col gap-8">
      <PageHeading
        title="Your organizations"
        subtitle="Every association and club office you belong to."
        actions={
          <Button onClick={() => setDialogOpen(true)}>
            <Plus className="size-4" />
            New organization
          </Button>
        }
      />

      {isPending ? <LoadingState label="Loading organizations" /> : null}

      {error ? (
        <ErrorState
          message={error instanceof ApiError ? error.detail : 'The list could not be loaded.'}
          onRetry={() => void refetch()}
        />
      ) : null}

      {organizations?.length === 0 ? (
        <EmptyState
          title="No organizations yet"
          description="An organization holds your leagues, clubs and players, and decides who may edit them."
          action={<Button onClick={() => setDialogOpen(true)}>Create your first organization</Button>}
        />
      ) : null}

      {organizations && organizations.length > 0 ? (
        <div className="grid gap-3 sm:grid-cols-2">
          {organizations.map((organization) => (
            <OrganizationCard key={organization.id} organization={organization} />
          ))}
        </div>
      ) : null}

      <CreateOrganizationDialog open={dialogOpen} onClose={() => setDialogOpen(false)} />
    </div>
  )
}
