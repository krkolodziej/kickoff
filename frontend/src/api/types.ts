/**
 * The API contract, written by hand.
 *
 * Without API Platform there is no OpenAPI document to generate these from, and annotating
 * the controllers well enough to generate *good* types is a separate job. Hand-written
 * types are honest about that: they are a promise the tests on the backend keep, and the
 * shared error envelope means a mismatch shows up as a type error rather than as undefined
 * at runtime.
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
