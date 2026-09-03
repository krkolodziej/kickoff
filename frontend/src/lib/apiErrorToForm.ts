import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

import { ApiError } from '@/api/client'

/**
 * Moves a failed request's `fields` onto the form that produced it.
 *
 * The backend already emits snake_case keys that match the form's field names, which is the
 * whole reason its violation formatter converts property paths — a camelCase key here would
 * simply never match, and the user would see a form that refused to submit with nothing
 * marked wrong.
 *
 * Returns the message that belongs at form level: anything not attributable to a field.
 */
export function applyApiErrorToForm<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  knownFields: readonly Path<T>[],
): string | null {
  if (!(error instanceof ApiError)) {
    return 'Something went wrong. Please try again.'
  }

  if (!error.fields) {
    return error.detail
  }

  const unattached: string[] = []

  for (const [field, messages] of Object.entries(error.fields)) {
    const message = messages.join(' ')

    if ((knownFields as readonly string[]).includes(field)) {
      setError(field as Path<T>, { type: 'server', message })
    } else {
      unattached.push(message)
    }
  }

  return unattached.length > 0 ? unattached.join(' ') : null
}
