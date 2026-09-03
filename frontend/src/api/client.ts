/**
 * The single door between this application and the API.
 *
 * Two rules shape everything below.
 *
 * 1. The access token lives in a module variable and nowhere else. Not localStorage, not
 *    sessionStorage, not a cookie readable by script. Anything a script can read, an
 *    injected script can read too, and a durable credential in storage survives the tab it
 *    was stolen from.
 *
 * 2. The refresh token is never touched here at all. It is an httpOnly cookie scoped to
 *    /api/v1/token, so the browser attaches it to exactly one endpoint and JavaScript
 *    cannot see it. `credentials: 'include'` is what lets it travel.
 *
 * The trade-off worth being able to state out loud: this is resistant to token theft via
 * XSS, and in exchange it introduces a CSRF surface on the refresh endpoint, mitigated by
 * SameSite=Lax, the POST-only method, and the narrow cookie path.
 */

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

/** Endpoints where a 401 is the answer, not a signal to refresh and try again. */
const NEVER_REFRESH = ['/auth/login', '/auth/register', '/token/refresh']

export type FieldErrors = Record<string, string[]>

export class ApiError extends Error {
  // Written out rather than declared as constructor parameter properties: the tsconfig
  // enables `erasableSyntaxOnly`, so every TypeScript construct has to disappear by
  // deleting it, and parameter properties emit real assignments.
  readonly status: number
  readonly code: string
  readonly detail: string
  readonly fields?: FieldErrors

  constructor(status: number, code: string, detail: string, fields?: FieldErrors) {
    super(detail)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.detail = detail
    this.fields = fields
  }

  /** True when the failure is attributable to individual form inputs. */
  get isValidation(): boolean {
    return this.code === 'validation_error' || this.code === 'invalid_payload'
  }
}

let accessToken: string | null = null

export function setAccessToken(token: string | null): void {
  accessToken = token
}

export function getAccessToken(): string | null {
  return accessToken
}

type SessionEndedHandler = () => void

let onSessionEnded: SessionEndedHandler | null = null

/**
 * Registered once, by the app shell. Without it an expired session leaves every query
 * stuck in an error state instead of returning the user to the sign-in screen.
 */
export function setSessionEndedHandler(handler: SessionEndedHandler | null): void {
  onSessionEnded = handler
}

export interface ApiRequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE'
  body?: unknown
  signal?: AbortSignal
}

async function send(path: string, options: ApiRequestOptions): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  if (accessToken) {
    headers.Authorization = `Bearer ${accessToken}`
  }

  return fetch(`${API_BASE_URL}${path}`, {
    method: options.method ?? 'GET',
    headers,
    credentials: 'include',
    signal: options.signal,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  })
}

let refreshInFlight: Promise<boolean> | null = null

async function requestNewAccessToken(): Promise<boolean> {
  try {
    const response = await fetch(`${API_BASE_URL}/token/refresh`, {
      method: 'POST',
      credentials: 'include',
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      return false
    }

    const data = (await response.json()) as { token: string }
    setAccessToken(data.token)

    return true
  } catch {
    return false
  }
}

/**
 * Collapses concurrent refreshes into one.
 *
 * On a page load half a dozen queries fire at once, all with no token, all getting 401.
 * Without this they would each POST to /token/refresh; with rotation enabled the first
 * would invalidate the token the others are still carrying and the user would be thrown
 * out on every reload. React StrictMode's double effect invocation makes this a certainty
 * in development rather than a race you might get away with.
 */
function refreshAccessToken(): Promise<boolean> {
  refreshInFlight ??= requestNewAccessToken().finally(() => {
    refreshInFlight = null
  })

  return refreshInFlight
}

async function parse<T>(response: Response): Promise<T> {
  if (response.status === 204) {
    return undefined as T
  }

  const text = await response.text()
  const payload: unknown = text === '' ? null : JSON.parse(text)

  if (response.ok) {
    return payload as T
  }

  const envelope = (payload ?? {}) as {
    detail?: string
    code?: string
    fields?: FieldErrors
  }

  throw new ApiError(
    response.status,
    envelope.code ?? 'api_error',
    envelope.detail ?? 'The request failed.',
    envelope.fields,
  )
}

export async function apiFetch<T>(path: string, options: ApiRequestOptions = {}): Promise<T> {
  let response = await send(path, options)

  if (response.status === 401 && !NEVER_REFRESH.some((prefix) => path.startsWith(prefix))) {
    // Whether a session was actually lost, or there simply never was one. On a cold load
    // there is no token in memory: the 401, the failed refresh and the resulting error are
    // the normal way an anonymous visitor is recognised, and announcing a session ending
    // there would send the app round the same loop for ever.
    const hadSession = accessToken !== null

    if (await refreshAccessToken()) {
      response = await send(path, options)
    } else {
      setAccessToken(null)

      if (hadSession) {
        onSessionEnded?.()
      }
    }
  }

  return parse<T>(response)
}
