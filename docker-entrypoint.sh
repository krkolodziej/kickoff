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

# --- serve -------------------------------------------------------------------------------
#
# A SERVER_NAME with no host is what tells Caddy to serve plain HTTP on that port and skip
# its automatic HTTPS: the platform terminates TLS in front of us, and a container trying to
# solve an ACME challenge behind a proxy fails in confusing ways.
export SERVER_NAME=":${PORT:-8080}"

exec frankenphp run --config /etc/caddy/Caddyfile
