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

export interface League {
  id: number
  organization_id: number
  name: string
  slug: string
  description: string
  created_at: string
}

export interface Team {
  id: number
  organization_id: number
  name: string
  short_name: string
  slug: string
  created_at: string
}

export interface Player {
  id: number
  organization_id: number
  first_name: string
  last_name: string
  full_name: string
  date_of_birth: string | null
  created_at: string
}

export type PlayerPosition = 'GOALKEEPER' | 'DEFENDER' | 'MIDFIELDER' | 'FORWARD'

export const PLAYER_POSITIONS = [
  'GOALKEEPER',
  'DEFENDER',
  'MIDFIELDER',
  'FORWARD',
] as const satisfies readonly PlayerPosition[]

export interface Season {
  id: number
  league_id: number
  name: string
  start_date: string
  end_date: string | null
  created_at: string
}

export interface SeasonTeam {
  id: number
  season_id: number
  team_id: number
  team_name: string
  team_short_name: string
  squad_size: number
}

export interface RosterEntry {
  id: number
  season_team_id: number
  player_id: number
  player_name: string
  shirt_number: number | null
  position: PlayerPosition | null
  captain: boolean
}

export interface SeasonPayload {
  name: string
  start_date: string
  end_date?: string | null
}

export interface RosterEntryPayload {
  player_id: number
  shirt_number?: number | null
  position?: PlayerPosition | null
  captain: boolean
}

export interface LeaguePayload {
  name: string
  slug?: string
  description: string
}

export interface TeamPayload {
  name: string
  slug?: string
  short_name: string
}

export interface PlayerPayload {
  first_name: string
  last_name: string
  date_of_birth?: string | null
}

/**
 * Collections are paginated only when asked. Without `page` or `page_size` the endpoint
 * answers with a bare array, which is what most screens in this application want — and the
 * type says so, rather than pretending every list is an envelope.
 */
export interface ResultPage<T> {
  count: number
  page: number
  page_size: number
  next: number | null
  previous: number | null
  results: T[]
}

export interface ListParams {
  search?: string
  order?: string
  page?: number
  pageSize?: number
}

/** Whether a role may create, edit and delete inside its organization. */
export function canManage(role: OrganizationRole): boolean {
  return role === 'OWNER' || role === 'ADMIN'
}

export function listQueryString({ search, order, page, pageSize }: ListParams = {}): string {
  const params = new URLSearchParams()

  if (search) params.set('search', search)
  if (order) params.set('order', order)
  if (page !== undefined) params.set('page', String(page))
  if (pageSize !== undefined) params.set('page_size', String(pageSize))

  const query = params.toString()

  return query === '' ? '' : `?${query}`
}
