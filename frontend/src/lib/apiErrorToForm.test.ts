import { describe, expect, it, vi } from 'vitest'

import { ApiError } from '@/api/client'

import { applyApiErrorToForm } from './apiErrorToForm'

type Values = { email: string; password: string }

describe('applyApiErrorToForm', () => {
  it('attaches each field message to its input', () => {
    const setError = vi.fn()

    const formLevel = applyApiErrorToForm<Values>(
      new ApiError(422, 'validation_error', 'Request validation failed.', {
        email: ['Enter a valid email address.'],
      }),
      setError,
      ['email', 'password'],
    )

    expect(setError).toHaveBeenCalledWith('email', {
      type: 'server',
      message: 'Enter a valid email address.',
    })
    expect(formLevel).toBeNull()
  })

  it('lifts messages for unknown fields to form level rather than dropping them', () => {
    const setError = vi.fn()

    // `_` is what the backend uses for a violation on the object as a whole. Silently
    // ignoring it would leave the form refusing to submit with nothing marked wrong.
    const formLevel = applyApiErrorToForm<Values>(
      new ApiError(422, 'validation_error', 'Request validation failed.', {
        _: ['That combination is not allowed.'],
      }),
      setError,
      ['email', 'password'],
    )

    expect(setError).not.toHaveBeenCalled()
    expect(formLevel).toBe('That combination is not allowed.')
  })

  it('falls back to the envelope detail when there are no field errors', () => {
    const formLevel = applyApiErrorToForm<Values>(
      new ApiError(401, 'invalid_credentials', 'Invalid credentials.'),
      vi.fn(),
      ['email', 'password'],
    )

    expect(formLevel).toBe('Invalid credentials.')
  })

  it('never shows a raw exception to the user', () => {
    const formLevel = applyApiErrorToForm<Values>(new TypeError('Failed to fetch'), vi.fn(), [
      'email',
    ])

    expect(formLevel).toBe('Something went wrong. Please try again.')
  })
})
