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

/**
 * Where the demonstration sign-in wants the visitor to land.
 *
 * Ids rather than a path: every address in this application is built on this side, and the
 * one place the server writes a path — a stored notification's `link` — documents itself as
 * the exception. Null when the deployment is enabled but has nothing seeded yet, in which
 * case the caller falls back to the organization list.
 */
export interface DemoEntry {
  organization_id: number
  league_id: number
  season_id: number
}

/** Only `/auth/demo` carries this, which is why it is not an optional field on AuthSession. */
export interface DemoSession extends AuthSession {
  demo: DemoEntry | null
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
  league_count: number
  team_count: number
  player_count: number
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

/** Enough of a season to name it and to build a link to it. */
export interface SeasonRef {
  id: number
  league_id: number
  name: string
  start_date: string
}

export interface League {
  id: number
  organization_id: number
  name: string
  slug: string
  description: string
  created_at: string
  season_count: number
  latest_season: SeasonRef | null
}

export interface Team {
  id: number
  organization_id: number
  name: string
  short_name: string
  slug: string
  created_at: string
  /** The current season's squad, not every player who has ever worn the shirt. */
  squad_size: number
  seasons_played: number
  latest_season: SeasonRef | null
}

/**
 * Where a player currently turns out.
 *
 * None of this belongs to the person: a number and a position are properties of a squad
 * entry, and they change when the player does. Null when nobody has registered them.
 */
export interface PlayerSquad {
  season_id: number
  season_name: string
  league_id: number
  team_id: number
  team_name: string
  team_short_name: string
  shirt_number: number | null
  position: PlayerPosition | null
  captain: boolean
}

export interface Player {
  id: number
  organization_id: number
  first_name: string
  last_name: string
  full_name: string
  date_of_birth: string | null
  created_at: string
  current_squad: PlayerSquad | null
  /** Whole years, computed by the server so that "today" has one definition. */
  age: number | null
  goals: number
  yellow_cards: number
  red_cards: number
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

export type MatchStatus = 'SCHEDULED' | 'LIVE' | 'FINISHED' | 'CANCELLED' | 'POSTPONED'

export type MatchEventType = 'GOAL' | 'YELLOW_CARD' | 'RED_CARD' | 'SUBSTITUTION'

export const MATCH_EVENT_TYPES = [
  'GOAL',
  'YELLOW_CARD',
  'RED_CARD',
  'SUBSTITUTION',
] as const satisfies readonly MatchEventType[]

export interface Fixture {
  id: number
  season_id: number
  round_number: number
  leg: number
  home_team_id: number
  home_team_name: string
  home_team_short_name: string
  away_team_id: number
  away_team_name: string
  away_team_short_name: string
  kick_off_at: string | null
  status: MatchStatus
  home_score: number
  away_score: number
  started_at: string | null
  finished_at: string | null
  /**
   * What the server would accept right now. Read rather than recomputed, so the buttons and
   * the state machine cannot drift apart — the client owns no copy of the rules.
   */
  allowed_transitions: MatchStatus[]
}

export interface MatchEvent {
  id: number
  fixture_id: number
  type: MatchEventType
  minute: number
  team_id: number
  home: boolean
  player_id: number
  player_name: string
  related_player_id: number | null
  related_player_name: string | null
}

export interface MatchEventPayload {
  type: MatchEventType
  minute: number
  team_id: number
  player_id: number
  related_player_id?: number | null
}

/** A match still being played is the only thing worth polling for. */
export function isLive(fixture: Fixture): boolean {
  return fixture.status === 'LIVE'
}

export interface GenerateFixturesPayload {
  double_round: boolean
  first_round_on?: string | null
  days_between_rounds: number
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
 * One line of the league table.
 *
 * Nothing here is stored on the server either: every number is derived from finished matches
 * on each request, so the table cannot disagree with the results behind it.
 */
export interface StandingRow {
  position: number
  team_id: number
  team_name: string
  played: number
  won: number
  drawn: number
  lost: number
  goals_for: number
  goals_against: number
  goal_difference: number
  points: number
}

/**
 * One player's season.
 *
 * Counted from match events, and from matches that are being played as well as finished ones
 * — a goal scored in the second half is a goal. The table waits for full time; this does not.
 */
export interface PlayerStatisticsRow {
  player_id: number
  first_name: string
  last_name: string
  team_id: number
  team_name: string
  goals: number
  yellow_cards: number
  red_cards: number
}

export type NotificationType = 'MATCH_FINISHED' | 'KICK_OFF_REMINDER'

/**
 * One line in the bell.
 *
 * `link` is a path inside this application rather than an absolute URL, so the client routes
 * to it instead of reloading the page — and so a stored notification does not go stale the
 * day the host changes.
 */
export interface AppNotification {
  id: number
  type: NotificationType
  title: string
  body: string
  link: string
  organization_id: number
  organization_name: string
  created_at: string
  read_at: string | null
}

/**
 * One line of a club's history.
 *
 * `position` is null for every season but the one being played. A club's own record comes
 * from its own fixtures; where it *finished* needs the whole league table for that season
 * computed, which is worth doing once and not once per season of history.
 */
export interface ClubSeasonRow {
  season_id: number
  season_name: string
  league_id: number
  league_name: string
  start_date: string
  squad_size: number
  played: number
  won: number
  drawn: number
  lost: number
  goals_for: number
  goals_against: number
  goal_difference: number
  points: number
  position: number | null
}

/** One screen, one request — see the backend's ClubProfile for why it is not three. */
export interface TeamProfile {
  team: Team
  latest_season_id: number | null
  squad: RosterEntry[]
  seasons: ClubSeasonRow[]
}

/** One line of a player's career: where they were registered, and what they did there. */
export interface PlayerSeasonRow {
  season_id: number
  season_name: string
  league_id: number
  league_name: string
  team_id: number
  team_name: string
  shirt_number: number | null
  position: PlayerPosition | null
  captain: boolean
  goals: number
  yellow_cards: number
  red_cards: number
}

export interface PlayerProfile {
  player: Player
  seasons: PlayerSeasonRow[]
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
