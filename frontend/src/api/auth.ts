import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import { apiFetch, setAccessToken } from './client'
import { qk } from './keys'
import type { AuthSession, LoginPayload, RegisterPayload, User } from './types'

async function login(payload: LoginPayload): Promise<AuthSession> {
  return apiFetch<AuthSession>('/auth/login', { method: 'POST', body: payload })
}

async function register(payload: RegisterPayload): Promise<AuthSession> {
  return apiFetch<AuthSession>('/auth/register', { method: 'POST', body: payload })
}

/**
 * Signs in the demonstration account, which needs no credential because there is none to
 * give: the server holds the one account this can reach, and it is an administrator rather
 * than the owner, so a visitor can run the league but not delete it.
 */
async function signInAsDemo(): Promise<AuthSession> {
  return apiFetch<AuthSession>('/auth/demo', { method: 'POST' })
}

async function fetchCurrentUser(): Promise<User> {
  return apiFetch<User>('/auth/me')
}

/**
 * The whole session model is this one query.
 *
 * On a cold load there is no access token in memory, so the request 401s, the client
 * silently refreshes from the httpOnly cookie and retries, and the user is signed in
 * again — without a token ever having been persisted anywhere script can reach. When there
 * is no valid cookie either, the refresh fails, the query errors, and the guards below read
 * that as "signed out".
 */
export function useCurrentUser() {
  const query = useQuery({
    queryKey: qk.currentUser,
    queryFn: fetchCurrentUser,
    retry: false,
    staleTime: 5 * 60 * 1000,
  })

  return {
    user: query.data ?? null,
    isResolving: query.isPending,
  }
}

export function useLogin() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: login,
    onSuccess: (session) => {
      setAccessToken(session.token)
      queryClient.setQueryData(qk.currentUser, session.user)
    },
  })
}

export function useDemoSignIn() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: signInAsDemo,
    onSuccess: (session) => {
      setAccessToken(session.token)
      queryClient.setQueryData(qk.currentUser, session.user)
    },
  })
}

export function useRegister() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: register,
    onSuccess: (session) => {
      setAccessToken(session.token)
      queryClient.setQueryData(qk.currentUser, session.user)
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: async () => {
      try {
        await apiFetch<void>('/auth/logout', { method: 'POST' })
      } catch {
        // Signing out must never fail visibly. If the token has already expired the server
        // has nothing left to revoke, which is the outcome we wanted anyway.
      }
    },
    onSettled: () => {
      setAccessToken(null)
      queryClient.clear()
      // Seeded, not left empty: a missing entry would be refetched the instant the guard
      // re-renders, costing a pointless 401 and a pointless refresh attempt.
      queryClient.setQueryData(qk.currentUser, null)
    },
  })
}
