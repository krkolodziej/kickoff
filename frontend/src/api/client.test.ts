import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { ApiError, apiFetch, getAccessToken, setAccessToken } from './client'

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('apiFetch', () => {
  beforeEach(() => {
    setAccessToken(null)
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('parses the error envelope into an ApiError', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(422, {
        detail: 'Request validation failed.',
        code: 'validation_error',
        fields: { password_confirm: ['The two passwords do not match.'] },
      }),
    )

    const error = await apiFetch('/auth/register', { method: 'POST', body: {} }).catch(
      (caught: unknown) => caught,
    )

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).code).toBe('validation_error')
    expect((error as ApiError).fields?.password_confirm).toHaveLength(1)
    expect((error as ApiError).isValidation).toBe(true)
  })

  it('refreshes once and retries when the access token has expired', async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce(jsonResponse(401, { detail: 'expired', code: 'token_expired' }))
      .mockResolvedValueOnce(jsonResponse(200, { token: 'fresh-token' }))
      .mockResolvedValueOnce(jsonResponse(200, { id: 1 }))

    await expect(apiFetch('/auth/me')).resolves.toEqual({ id: 1 })
    expect(getAccessToken()).toBe('fresh-token')

    const retriedInit = vi.mocked(fetch).mock.calls[2][1]
    const retriedHeaders = (retriedInit?.headers ?? {}) as Record<string, string>
    expect(retriedHeaders.Authorization).toBe('Bearer fresh-token')
  })

  it('collapses concurrent refreshes into a single request', async () => {
    // Six queries firing on a cold page load must not burn six refresh tokens.
    vi.mocked(fetch).mockImplementation((input) => {
      const url = String(input)

      if (url.endsWith('/token/refresh')) {
        return Promise.resolve(jsonResponse(200, { token: 'fresh-token' }))
      }

      return Promise.resolve(
        getAccessToken() === 'fresh-token'
          ? jsonResponse(200, { ok: true })
          : jsonResponse(401, { detail: 'no token', code: 'authentication_required' }),
      )
    })

    await Promise.all([apiFetch('/a'), apiFetch('/b'), apiFetch('/c')])

    const refreshCalls = vi
      .mocked(fetch)
      .mock.calls.filter(([input]) => String(input).endsWith('/token/refresh'))

    expect(refreshCalls).toHaveLength(1)
  })

  it('does not try to refresh when the login itself is rejected', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(401, { detail: 'Invalid credentials.', code: 'invalid_credentials' }),
    )

    await expect(apiFetch('/auth/login', { method: 'POST', body: {} })).rejects.toBeInstanceOf(
      ApiError,
    )

    expect(vi.mocked(fetch)).toHaveBeenCalledTimes(1)
  })

  it('returns nothing for a 204', async () => {
    vi.mocked(fetch).mockResolvedValueOnce(new Response(null, { status: 204 }))

    await expect(apiFetch('/auth/logout', { method: 'POST' })).resolves.toBeUndefined()
  })
})
