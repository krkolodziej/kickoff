/**
 * The API contract, written by hand.
 *
 * Without API Platform there is no OpenAPI document to generate these from, and annotating
 * the controllers well enough to generate *good* types is a separate job. Hand-written
 * types are honest about that: they are a promise the backend's tests keep, and the shared
 * error envelope means a mismatch shows up as a type error rather than as undefined at
 * runtime.
 *
 * Everything is snake_case because that is what the wire carries — the backend's serializer
 * converts from PHP's camelCase in one place, and nothing translates again on this side.
 */

export interface User {
  id: number
  email: string
  first_name: string
  last_name: string
  full_name: string
}

export interface AuthSession {
  token: string
  user: User
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  email: string
  password: string
  password_confirm: string
  first_name: string
  last_name: string
}

/**
 * Authority is per organization, so it travels with each organization rather than sitting
 * on the account. The same person can own one competition and merely watch another.
 */
export type OrganizationRole = 'OWNER' | 'ADMIN' | 'MEMBER'

/** Roles the API will accept in a request body. Ownership is established, never assigned. */
export const ASSIGNABLE_ROLES = ['ADMIN', 'MEMBER'] as const satisfies readonly OrganizationRole[]

export interface Organization {
  id: number
  name: string
  slug: string
  my_role: OrganizationRole
  member_count: number
  created_at: string
}

export interface Membership {
  id: number
  user_id: number
  email: string
  full_name: string
  role: OrganizationRole
  created_at: string
}

export interface OrganizationPayload {
  name: string
  slug?: string
}

export interface AddMemberPayload {
  email: string
  role: OrganizationRole
}

/** Whether a role may create, edit and delete inside its organization. */
export function canManage(role: OrganizationRole): boolean {
  return role === 'OWNER' || role === 'ADMIN'
}
