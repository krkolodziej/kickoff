# Kickoff

Run an amateur football league: register clubs and squads, generate a season's calendar,
record what happens minute by minute in each match, and read the table and player statistics
straight off those events.

Nothing a reader sees is typed in twice. A goal is an event on a match; the score, the
league table and the top-scorer list are all derived from those events, so they cannot
disagree with one another.

**Symfony 7.4 LTS** REST API + **React 19** single-page application.

---

## Status

The project is built in stages, each one a pull request. See the
[stage index](#stages) below for what is in and what is next.

---

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| API | Symfony 7.4 LTS, PHP 8.2 | Hand-written controllers, Serializer and Validator — no API Platform, so the framework's own mechanisms stay visible |
| Persistence | Doctrine ORM 3, PostgreSQL 17 | Migrations are checked in and `doctrine:schema:validate` runs in CI |
| Authentication | LexikJWTAuthenticationBundle + Gesdinet refresh tokens | Short-lived access token in memory, refresh token in an httpOnly cookie |
| SPA | React 19, TypeScript, Vite 8 | |
| Server state | TanStack Query v5 | Hierarchical query keys, so invalidating a season invalidates everything derived from it |
| Styling | Tailwind CSS v4, light and dark | Tokens in `@theme`, no `tailwind.config.js` — v4 does not have one |
| Forms | react-hook-form + zod | The API's `fields` object maps onto the form without a translation layer |
| Tests | PHPUnit, Foundry, DAMA · Vitest | |

---

## Requirements

- PHP **8.2+** with `pdo_pgsql`, `intl`, `openssl`, `sodium`, `zip`
- Composer 2
- Node 22+ and [pnpm](https://pnpm.io) 10 (`npm i -g pnpm@10.30.2`)
- PostgreSQL 14+

### If you are on XAMPP for Windows

Three extensions ship disabled and all three are needed — `sodium` because Lexik's JWT
library requires it, `zip` because Composer otherwise has to clone every package from source,
and `pdo_pgsql` to reach the database at all. Uncomment them in `C:\xampp\php\php.ini`:

```ini
extension=pdo_pgsql
extension=sodium
extension=zip
```

Confirm with `php -m | findstr /i "sodium zip pgsql"`.

XAMPP ships no PostgreSQL server, so install one separately:

```bash
winget install -e --id PostgreSQL.PostgreSQL.17
```

---

## Setup

```bash
git clone https://github.com/krkolodziej/kickoff.git
cd kickoff
```

### API

```bash
cd backend
composer install
cp .env .env.local   # then set DATABASE_URL for your machine
```

`DATABASE_URL` names the server and its major version:

```dotenv
DATABASE_URL="postgresql://postgres:kickoff@127.0.0.1:5432/kickoff?serverVersion=17&charset=utf8"
```

The major version is what Doctrine picks its platform from, and a wrong value produces
migrations the server rejects or a `migrations:diff` that never comes back empty. It is
declared once in `config/packages/doctrine.yaml`, so a connection string without it still
works — `serverVersion` in the URL overrides that default when you need a different one.
Check what you actually have with `php bin/console dbal:run-sql "SELECT version()"`.

Then create the schema and the signing keys:

```bash
composer db:setup
php bin/console lexik:jwt:generate-keypair
```

> On XAMPP, `lexik:jwt:generate-keypair` fails with `error:80000003:system library::No such
> process` because PHP's OpenSSL cannot find its configuration file. Point it at the copy
> XAMPP ships and run the command again:
>
> ```bash
> OPENSSL_CONF=C:/xampp/php/extras/ssl/openssl.cnf php bin/console lexik:jwt:generate-keypair
> ```

Start it:

```bash
symfony server:start -d      # http://127.0.0.1:8000
```

### SPA

```bash
cd frontend
pnpm install
pnpm run dev                 # http://localhost:5173
```

Vite proxies `/api` to the API, so both halves are served from one origin in development.
That is not cosmetic: the refresh token is a cookie scoped to `/api/v1/token`, and a single
origin keeps it working without CORS credentials negotiation.

---

## Development

| | |
| --- | --- |
| API | `cd backend && symfony server:start -d` |
| SPA | `cd frontend && pnpm run dev` |

`./dev.ps1` starts both.

### Checks

```bash
cd backend
composer test        # PHPUnit
composer stan        # PHPStan
composer cs          # php-cs-fixer, check only
```

```bash
cd frontend
pnpm run test
pnpm run lint
pnpm exec tsc -b
```

The test suite uses a separate database (`kickoff_test`, from `dbname_suffix`) and builds it
by running the real migrations, so a migration that no longer applies fails the suite rather
than a deployment. Create it once with `createdb -U postgres kickoff_test`, or let
`composer db:setup` do it.

---

## API

Base path `/api/v1`. Everything is JSON.

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| `GET` | `/health` | — | Liveness |
| `POST` | `/auth/register` | — | Create an account and sign it in |
| `POST` | `/auth/login` | — | Exchange email and password for an access token |
| `POST` | `/token/refresh` | refresh cookie | Exchange the refresh cookie for a new access token |
| `GET` | `/auth/me` | bearer | The signed-in account |
| `POST` | `/auth/logout` | bearer | Revoke every refresh token and clear the cookie |
| `GET` | `/organizations` | bearer | The organizations you belong to, with your role in each |
| `POST` | `/organizations` | bearer | Create one; you become its owner |
| `GET` | `/organizations/{id}` | member | One organization |
| `PATCH` | `/organizations/{id}` | admin | Rename it |
| `DELETE` | `/organizations/{id}` | owner | Delete it, and everything inside |
| `GET` | `/organizations/{id}/members` | member | The roster |
| `POST` | `/organizations/{id}/members` | admin | Add an existing account by email |
| `PATCH` | `/organizations/{id}/members/{membershipId}` | admin | Change someone's role |
| `DELETE` | `/organizations/{id}/members/{membershipId}` | admin | Remove them |
| `GET` `POST` | `/organizations/{id}/leagues` | member · admin | Competitions this organization runs |
| `GET` `PATCH` `DELETE` | `/organizations/{id}/leagues/{leagueId}` | member · admin | One league |
| `GET` `POST` | `/organizations/{id}/teams` | member · admin | Clubs, registered once and reused |
| `GET` `PATCH` `DELETE` | `/organizations/{id}/teams/{teamId}` | member · admin | One club |
| `GET` `POST` | `/organizations/{id}/players` | member · admin | People, separate from any squad |
| `GET` `PATCH` `DELETE` | `/organizations/{id}/players/{playerId}` | member · admin | One player |
| `GET` `POST` | `…/leagues/{leagueId}/seasons` | member · admin | Editions of a league |
| `GET` `PATCH` `DELETE` | `…/seasons/{seasonId}` | member · admin | One season |
| `GET` `POST` | `…/seasons/{seasonId}/teams` | member · admin | Clubs registered for the season |
| `DELETE` | `…/seasons/{seasonId}/teams/{seasonTeamId}` | admin | Withdraw a club |
| `GET` `POST` | `…/teams/{seasonTeamId}/roster` | member · admin | A club's squad for that season |
| `PATCH` `DELETE` | `…/roster/{rosterEntryId}` | admin | Number, position, captain, or removal |
| `GET` | `…/seasons/{seasonId}/fixtures` | member | The calendar, filterable by `round` and `team` |
| `POST` | `…/seasons/{seasonId}/fixtures/generate` | admin | Pair every registered club; refuses a second run |
| `DELETE` | `…/seasons/{seasonId}/fixtures` | admin | Clear the calendar |
| `GET` | `…/fixtures/{fixtureId}` | member | One match, with its score and what it may do next |
| `POST` | `…/fixtures/{fixtureId}/start` `/finish` `/cancel` `/postpone` `/reschedule` | admin | Move it through the machine |
| `GET` `POST` | `…/fixtures/{fixtureId}/events` | member · admin | The timeline; recording is append-only |

The calendar accepts `?status=LIVE,FINISHED`; an unrecognised value is a 400 rather than a
filter that quietly does nothing.

### The score is never typed in

A goal is an event, and the score moves in the **same transaction** that records it. There is
no endpoint that edits or deletes an event: the score is derived from those rows, so an
editable event is a score that can stop matching its own history without anything noticing. A
mistake is corrected by recording the truth.

Each match reports `allowed_transitions` — what the server would accept right now — so a client
disables a button instead of offering one that answers 409. The client keeps no copy of the
rules, so the two cannot drift apart.

Dates that are dates — a season's start and end, a player's date of birth — are emitted as
`2026-08-15`, not as RFC 3339. A timestamp would invent a midnight and a timezone the value
does not have, and a client an hour west could render the day before. `created_at` keeps the
full form, because that one really is an instant.

Authority is granted **per organization**, not globally: the same person can own one
competition and merely read another. "member", "admin" and "owner" above are roles inside the
organization named in the path, and they are checked by a voter — the account itself carries
no privileges.

### Not yours and not there are the same answer

An organization you are not a member of answers **404, never 403** — on every verb. A 403
would confirm the id is real, and iterating over ids while telling the two apart would map
the whole system to someone with no account in it. Every scoped query joins the caller's
membership, so there is no code path to a row outside it and no check anyone can forget.

### Collections

Every collection endpoint takes `search`, `order`, `page` and `page_size`.

Paging is **opt-in**. Without `page` or `page_size` the response is a plain array; with either,
it is an envelope:

```json
{ "count": 12, "page": 2, "page_size": 10, "next": null, "previous": 1, "results": [] }
```

`next` and `previous` are page numbers rather than URLs, so no response carries a hostname that
then has to be right behind a proxy, in tests and in a container.

`order` takes a field name, optionally prefixed with `-` to reverse it, and is checked against
a per-resource allow-list. An unknown field is a **400 naming the fields that are allowed** —
not a parameter silently ignored, which is how a list ends up in the wrong order in production
while every response still looks plausible.

### Errors

Every failure under `/api` uses one envelope:

```json
{
  "detail": "Request validation failed.",
  "code": "validation_error",
  "fields": { "password_confirm": ["The two passwords do not match."] }
}
```

`detail` is for people, `code` is for the client to branch on, and `fields` is present only
when the failure is attributable to individual inputs. Field keys are snake_case, matching
the request body — so a client can apply them to its form without a lookup table.

| `code` | Status | |
| --- | --- | --- |
| `validation_error` | 422 | The body parsed, the values are wrong |
| `invalid_payload` | 400 | The body did not parse |
| `invalid_credentials` | 401 | Wrong email or password, or a spent refresh token |
| `token_expired` | 401 | Access token past its lifetime — refresh and retry |
| `token_invalid` | 401 | Access token forged or malformed — sign in again |
| `authentication_required` | 401 | No credential presented |
| `permission_denied` | 403 | Authenticated, not allowed |
| `not_found` | 404 | |
| `conflict` | 409 | Well-formed, but the current state forbids it |
| `already_a_member` | 409 | That account is already in the organization |
| `owner_membership_protected` | 403 | Ownership is not editable through the members API |
| `invalid_ordering` | 400 | `order` named a field this resource does not sort on |
| `season_name_taken` | 409 | That league already has a season with that name |
| `already_registered` | 409 | That club is already in this season |
| `already_in_squad` | 409 | That player is already in this squad |
| `squad_rule_violated` | 422 | A shirt number is taken, or the player belongs elsewhere |
| `fixtures_already_generated` | 409 | This season has a calendar; clear it first |
| `not_enough_clubs` | 409 | Fewer than two clubs are registered |
| `invalid_transition` | 409 | The match is not in a state that allows it; the message names what is |
| `match_not_live` | 409 | Events are only recorded while a match is being played |
| `match_event_rule_violated` | 422 | Wrong club, a player not in that squad, or a malformed substitution |

### Tokens

The access token is short-lived (15 minutes) and lives only in a JavaScript variable —
never in `localStorage`, because anything a script can read, an injected script can read
too. The refresh token is an httpOnly cookie scoped to `/api/v1/token`, so the browser sends
it to exactly one endpoint and no script can reach it at all. On a page reload the first
request 401s, the client refreshes from the cookie, and the session continues.

Concurrent refreshes are collapsed into one request. With token rotation enabled, six
queries firing on a cold load would otherwise burn six refresh tokens and sign the user
straight back out.

---

## Stages

| | | |
| --- | --- | --- |
| 1 | Foundation: accounts, JWT, the error envelope, the design system | ✅ |
| 2 | Organizations and per-organization roles | ✅ |
| 3a | Leagues, clubs, players, and the list machinery | ✅ |
| 3b | Seasons, squad registration, rosters | ✅ |
| 4 | Round-robin fixture generation | ✅ |
| 5 | Matches, the state machine, goals and cards | ✅ |
| 6 | Standings, player statistics, demo data | |
| 7 | Messenger, notifications, scheduled reminders | |
| 8 | Realtime match updates, hardening | |

Implementation notes, in Polish, with `file:line` references: [`docs/NOTES.md`](docs/NOTES.md).

---

## Deployment

One image holds both halves: the SPA is built in the first stage, and Caddy — with PHP
embedded, via FrankenPHP — serves its hashed assets straight off disk while everything it
cannot find falls through to Symfony. That is not tidiness. The refresh token is a cookie
scoped to `/api/v1/token`, and a single origin means it works with no CORS credentials
negotiation at all.

| | |
| --- | --- |
| Application | Render, Docker, free plan |
| Database | Neon — a free Render Postgres is deleted after thirty days; a free Neon project is not |
| Trigger | `autoDeployTrigger: checksPass` — merging to `main` deploys **only once CI is green** |
| Release | Migrations run from the entrypoint; a pre-deploy hook is a paid feature |

CI builds this same Dockerfile, starts the container and asks it for `/api/v1/health` and for
`/dashboard`. An image that will not build or will not boot fails in the pull request, and
since deployment waits for green checks, it can never reach production.

### First-time setup

**1. A database.** Create a free project at [neon.tech](https://neon.tech) and copy the
*pooled* connection string — paste it as it comes:

```
postgresql://USER:PASSWORD@HOST/DB?sslmode=require
```

Doctrine takes the major version from `config/packages/doctrine.yaml`, so there is nothing to
append. If Neon ever gives you something other than 17, change it there rather than here.

**2. A signing keypair — a fresh one, not the one in `.env`.**

```bash
cd backend
php bin/console lexik:jwt:generate-keypair --overwrite
base64 -w0 config/jwt/private.pem > ../jwt-private.b64
base64 -w0 config/jwt/public.pem  > ../jwt-public.b64
```

> On XAMPP, prefix the first command with `OPENSSL_CONF=C:/xampp/php/extras/ssl/openssl.cnf`.

The keypair is configuration rather than a build artefact, and deliberately so. Generating one
at start would mint a new pair every time the container wakes — and a free instance sleeps
after fifteen quiet minutes, so every visitor would sign out the last one. Baking it into the
image would put a private key in a public repository's build.

**3. The service.** In Render, *New → Blueprint* and point it at this repository;
`render.yaml` describes everything else. Fill in the four values it asks for:

| Variable | Value |
| --- | --- |
| `DATABASE_URL` | the Neon string from step 1 |
| `JWT_SECRET_KEY_B64` | contents of `jwt-private.b64` |
| `JWT_PUBLIC_KEY_B64` | contents of `jwt-public.b64` |
| `JWT_PASSPHRASE` | the passphrase from step 2 |

Then delete the two `.b64` files. They are gitignored, but there is no reason to keep a
private key lying about.

Afterwards every merge to `main` runs CI and, if it passes, deploys.

### What to expect on the free plan

The instance sleeps after fifteen minutes without traffic and takes about a minute to wake, so
the first request after a quiet spell is slow. Nothing is lost by it: state lives in Neon, and
the keys come from the environment, so a sleep does not sign anybody out.

---

## Licence

MIT.
