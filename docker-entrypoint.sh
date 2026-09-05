#!/bin/sh
set -e

# --- signing keys ---------------------------------------------------------------------
#
# The JWT keypair cannot be generated here, and cannot be baked into the image either.
#
# Generating on start would mint a new pair every time the container wakes, and a free
# instance sleeps after fifteen quiet minutes — so every visitor would be signed out by the
# next visitor's arrival. Baking it into the image would at least survive a sleep, but a
# private key in a public repository's build is a private key in a public repository.
#
# So it arrives as configuration, base64 so it survives being pasted into a web form.
if [ -n "${JWT_SECRET_KEY_B64:-}" ]; then
    mkdir -p config/jwt
    printf '%s' "${JWT_SECRET_KEY_B64}" | base64 -d > config/jwt/private.pem
    printf '%s' "${JWT_PUBLIC_KEY_B64}" | base64 -d > config/jwt/public.pem
    chmod 600 config/jwt/private.pem
fi

# --- release ---------------------------------------------------------------------------
#
# A pre-deploy hook is a paid feature on Render's free plan, so the release work happens on
# start instead. `migrate` is a no-op once applied, which matters: this runs on every wake
# from sleep, not only on every deploy.
#
# --allow-no-migration so a container starting against an already-current database exits 0
# rather than treating "nothing to do" as a failure.
if [ "${RUN_RELEASE_ON_START:-false}" = "true" ]; then
    echo "release: applying migrations"
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

# --- demonstration data ---------------------------------------------------------------------
#
# In the background, and that is the whole point. The first run plays seventy-odd matches
# through the real domain services and takes about a minute; done before the server starts, it
# would miss the platform's health check and the deploy would be rolled back for no reason.
#
# So the server comes up immediately and the demo endpoint answers "not prepared yet" until the
# seeding finishes — which is exactly why that response says something specific instead of 404.
#
# Every later start is a single query: the seeder is idempotent and stops as soon as it finds
# the organization already there.
if [ "${SEED_DEMO_ON_START:-false}" = "true" ]; then
    echo "demo: seeding in the background"
    ( php bin/console app:seed:demo --no-interaction || echo "demo: seeding failed" ) &
fi

# --- realtime -----------------------------------------------------------------------------
#
# The hub runs inside this very process, so the application publishes to itself over the
# loopback. The port is not known until Render sets it, which is why this is computed here
# rather than declared as a static environment variable.
#
# The public URL is a path, not an absolute address: the browser resolves it against the origin
# it is already on, so nothing has to know the deployment's hostname.
export MERCURE_URL="http://127.0.0.1:${PORT:-8080}/.well-known/mercure"
export MERCURE_PUBLIC_URL="/.well-known/mercure"

# The hub is switched on through the placeholder the image leaves inside its own site block.
#
# Not through Caddyfile.d/: that directory is imported at the *global* level and expects whole
# site blocks, so a bare `mercure` directive dropped there is read as a site address and Caddy
# refuses to start. CI caught that in ninety seconds, which is the entire reason the image is
# built and booted there rather than only on the platform.
#
# Publisher and subscriber tokens share a secret because the same application mints both: it
# publishes updates, and it hands out narrow subscriber tokens after deciding, through the
# ordinary membership rules, who may watch what. There is no `anonymous`, so a subscriber
# without a token naming the topic receives nothing at all.
#
# Guarded, because Caddy refuses to start a hub with no subscriber key and that would take the
# whole site down over a missing optional secret. The clients already fall back to polling when
# there is no hub, so a deployment without the secret loses realtime and nothing else.
#
# Announced rather than skipped in silence: a feature that quietly does nothing is worse than
# one that is plainly off, and this line is the only place anybody would find out.
if [ -n "${MERCURE_JWT_SECRET:-}" ]; then
    export CADDY_SERVER_EXTRA_DIRECTIVES="mercure {
    publisher_jwt {env.MERCURE_JWT_SECRET}
    subscriber_jwt {env.MERCURE_JWT_SECRET}
}"
else
    echo "realtime: MERCURE_JWT_SECRET is not set, so the hub is off and clients will poll"
fi

# --- background work ---------------------------------------------------------------------
#
# The worker runs beside the web server in the same container, because a separate background
# service is a paid feature and one process is what the free plan gives.
#
# The loop is the supervisor. `messenger:consume` is told to stop after an hour — a worker
# holds the container it booted with, and a long-lived one accumulates memory and stale code
# — so something has to start it again, and `|| true` keeps a crash from taking the whole
# container down with it.
#
# The honest limitation: a free instance sleeps after fifteen quiet minutes and the worker
# sleeps with it. Queued work is not lost, because it is rows in a table, and it is picked up
# on the next wake. But the schedule only advances while somebody is using the application,
# so a reminder can arrive late. Moving the worker to its own always-on service is the fix,
# and it costs money rather than code.
if [ "${RUN_WORKER:-false}" = "true" ]; then
    echo "worker: consuming async and scheduler_reminders"
    (
        while true; do
            php bin/console messenger:consume async scheduler_reminders \
                --time-limit=3600 --memory-limit=96M --no-interaction || true
            sleep 5
        done
    ) &
fi

# --- serve -------------------------------------------------------------------------------
#
# A SERVER_NAME with no host is what tells Caddy to serve plain HTTP on that port and skip
# its automatic HTTPS: the platform terminates TLS in front of us, and a container trying to
# solve an ACME challenge behind a proxy fails in confusing ways.
export SERVER_NAME=":${PORT:-8080}"

exec frankenphp run --config /etc/caddy/Caddyfile
