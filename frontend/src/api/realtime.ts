import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'

import { apiFetch } from './client'
import { qk } from './keys'
import type { SeasonPath } from './seasons'

export type Transport = 'polling' | 'stream'

/**
 * Whether this build should try the hub at all.
 *
 * A flag rather than a probe, because "is there a hub" is a deployment fact, not something a
 * browser should discover by failing. Development has no hub unless somebody starts one, so
 * the default is the transport that always works.
 */
const REALTIME_ENABLED = import.meta.env.VITE_REALTIME === 'mercure'

interface Subscription {
  hub: string
  topic: string
}

function realtimePath({ organizationId, leagueId, seasonId }: SeasonPath, fixtureId: number): string {
  return `/organizations/${organizationId}/leagues/${leagueId}/seasons/${seasonId}/fixtures/${fixtureId}/realtime`
}

/**
 * Opens a stream for one match, and says which transport ended up in use.
 *
 * The contract this hook exists to keep: **it never leaves the caller without updates.** If
 * the flag is off, if the token endpoint refuses, if the hub is unreachable or dies mid-match,
 * it reports `polling` and the queries fall back to their timers. A realtime feature that
 * breaks the page when the hub goes down is worse than no realtime feature.
 *
 * What arrives is a signal, not a match — `{ fixture_id }` and nothing else — so the response
 * to it is to invalidate the cache and let the ordinary endpoints answer. That keeps every
 * authorisation decision in the REST API, where it is already made per role.
 */
export function useMatchStream(path: SeasonPath, fixtureId: number): Transport {
  const queryClient = useQueryClient()
  const [transport, setTransport] = useState<Transport>('polling')

  // Pulled apart before the effect because `path` is a fresh object on every render: listing
  // it as a dependency would tear the stream down and build it again several times a second.
  const { organizationId, leagueId, seasonId } = path

  useEffect(() => {
    if (!REALTIME_ENABLED) return

    let source: EventSource | null = null
    let cancelled = false

    const connect = async () => {
      let subscription: Subscription

      try {
        // POST rather than GET: the response sets an httpOnly cookie carrying a token that
        // names this one topic, and that is a side effect, not a read.
        subscription = await apiFetch<Subscription>(
          realtimePath({ organizationId, leagueId, seasonId }, fixtureId),
          { method: 'POST' },
        )
      } catch {
        // No token, no stream. The caller is already polling, so there is nothing to repair.
        return
      }

      if (cancelled) return

      // The server answers with a path rather than an absolute address, so nothing on either
      // side has to know the deployment's hostname. Resolving it against the current origin
      // also means the authorisation cookie is sent, which a cross-origin hub would not get.
      const url = new URL(subscription.hub, window.location.origin)
      url.searchParams.append('topic', subscription.topic)

      // withCredentials so the browser sends the authorisation cookie; EventSource cannot set
      // headers, which is the reason the token travels in a cookie in the first place.
      source = new EventSource(url, { withCredentials: true })

      source.onopen = () => {
        if (!cancelled) setTransport('stream')
      }

      source.onmessage = () => {
        void queryClient.invalidateQueries({
          queryKey: qk.fixture(organizationId, leagueId, seasonId, fixtureId),
        })
        void queryClient.invalidateQueries({
          queryKey: qk.matchEvents(organizationId, leagueId, seasonId, fixtureId),
        })
      }

      source.onerror = () => {
        // EventSource reconnects on its own, but a hub that has gone away would leave the page
        // silently stale while it tried. Falling back immediately means the timers restart and
        // the screen keeps moving; if the hub returns, a reload picks the stream up again.
        source?.close()
        source = null

        if (!cancelled) setTransport('polling')
      }
    }

    void connect()

    return () => {
      cancelled = true
      source?.close()
      setTransport('polling')
    }
  }, [queryClient, organizationId, leagueId, seasonId, fixtureId])

  return transport
}
