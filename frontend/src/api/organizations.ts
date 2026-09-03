import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch } from './client'
import { qk } from './keys'
import type {
  AddMemberPayload,
  Membership,
  Organization,
  OrganizationPayload,
  OrganizationRole,
} from './types'

export function useOrganizations(search?: string) {
  return useQuery({
    queryKey: qk.organizations(search),
    queryFn: () =>
      apiFetch<Organization[]>(
        `/organizations${search ? `?search=${encodeURIComponent(search)}` : ''}`,
      ),
  })
}

export function useOrganization(organizationId: number) {
  return useQuery({
    queryKey: qk.organization(organizationId),
    queryFn: () => apiFetch<Organization>(`/organizations/${organizationId}`),
  })
}

export function useCreateOrganization() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: OrganizationPayload) =>
      apiFetch<Organization>('/organizations', { method: 'POST', body: payload }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['organizations'] }),
  })
}

export function useRenameOrganization(organizationId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: OrganizationPayload) =>
      apiFetch<Organization>(`/organizations/${organizationId}`, {
        method: 'PATCH',
        body: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['organizations'] }),
  })
}

export function useDeleteOrganization(organizationId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => apiFetch<void>(`/organizations/${organizationId}`, { method: 'DELETE' }),
    onSuccess: () => {
      // The whole subtree is gone, not merely stale. Removing rather than invalidating
      // avoids a refetch that would answer 404 the moment it lands.
      queryClient.removeQueries({ queryKey: qk.organization(organizationId) })
      void queryClient.invalidateQueries({ queryKey: ['organizations'] })
    },
  })
}

export function useMembers(organizationId: number) {
  return useQuery({
    queryKey: qk.members(organizationId),
    queryFn: () => apiFetch<Membership[]>(`/organizations/${organizationId}/members`),
  })
}

export function useAddMember(organizationId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AddMemberPayload) =>
      apiFetch<Membership>(`/organizations/${organizationId}/members`, {
        method: 'POST',
        body: payload,
      }),
    // The organization key is invalidated too, because `member_count` on the organization
    // has just changed. Hierarchical keys make that one call rather than two.
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.organization(organizationId) }),
  })
}

export function useChangeMemberRole(organizationId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ membershipId, role }: { membershipId: number; role: OrganizationRole }) =>
      apiFetch<Membership>(`/organizations/${organizationId}/members/${membershipId}`, {
        method: 'PATCH',
        body: { role },
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.members(organizationId) }),
  })
}

export function useRemoveMember(organizationId: number) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (membershipId: number) =>
      apiFetch<void>(`/organizations/${organizationId}/members/${membershipId}`, {
        method: 'DELETE',
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: qk.organization(organizationId) }),
  })
}
