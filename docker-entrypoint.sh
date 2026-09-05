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
