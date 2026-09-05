# Decisions

Choices that shaped this application, why they were made, and what they cost. Written down
because the reasoning is the part that gets lost: the code shows what was decided, never what
was decided against.

Each entry says what the alternative was. A decision with no alternative is not a decision.

---

## 1. Hand-written controllers rather than API Platform

**Chosen:** controllers, the Serializer and the Validator, wired by hand.

API Platform would have produced this API's CRUD from the entities in an afternoon, with
OpenAPI documentation and filtering for nothing. It is the right tool for an application whose
endpoints are mostly its tables.

This one is not. Generating a season is not a POST to a collection; recording a goal moves a
score in the same transaction; a match refuses transitions the state machine does not allow.
Those are the parts worth building, and in a generated API each of them arrives as a custom
operation, a state processor or a listener — the framework's shape first, then an escape hatch
out of it for everything interesting.

**Cost:** every list endpoint is written by hand, and there is no OpenAPI document. Pagination,
filtering and ordering are a small piece of shared machinery (`ListQuery`, `Listing`) rather
than a configuration line.

---

## 2. PostgreSQL, arrived at the hard way

**Chosen:** PostgreSQL 17, on Neon.

The project started on MariaDB because that is what XAMPP ships. That was fine until
deployment, where it turned out that free permanent MySQL hosting effectively does not exist,
while the platform that builds Docker images for free offers PostgreSQL only.

The migration cost one afternoon and paid for itself immediately. Two tests that had been green
for weeks broke, both correctly: search had been relying on a case-insensitive collation rather
than on the query, and a partial unique index — which MariaDB cannot express — revealed that
the captain handover briefly held two captains, because Doctrine orders inserts ahead of
updates within one flush.

**Cost:** an afternoon, and one lesson worth more than the afternoon: a constraint the database
can enforce finds bugs no test was looking for.

---

## 3. A 404 where a 403 would be honest

**Chosen:** asking for a resource in an organization you do not belong to answers **404**.

403 is the accurate answer and it leaks. "You may not see organization 7" tells the asker that
organization 7 exists, and a loop over the ids maps out the entire installation. 404 says
nothing either way.

The rule is kept by construction rather than by discipline: `ScopeFactory` resolves the whole
chain — organization, league, season, fixture — in one query that **joins the caller's own
membership**. There is no code path that loads somebody else's row and then decides what to do
about it, because the query never returns one.

**Cost:** a genuine "this exists but is not yours" case is indistinguishable from a typo, which
would matter in an application with public resources and does not here.

---

## 4. Polling first, streaming later, both behind one hook

**Chosen:** ship polling in Stage 5; add Mercure in Stage 8 behind the same interface, with
polling as the fallback.

Realtime was always going to be a stage of its own, and building the live match screen on a
three-second timer meant the screen was finished and usable five stages before the hub existed.
When the hub arrived, the change was confined to one hook: no component knows which transport
it is on.

That fallback is not a leftover. A hub that is down, a token endpoint that refuses, a browser
on a network that eats server-sent events — all of them land on the timer, and the page keeps
working. A realtime feature that breaks the page when the hub goes down is worse than no
realtime feature.

**Cost:** two transports to reason about, and a flag to decide between them.

---

## 5. Integer identifiers, not UUIDs

**Chosen:** auto-incrementing integers as primary keys, exposed in URLs.

UUIDs would have hidden how many organizations exist and let a client mint an id before the
server saw it. Neither matters here: enumeration is already answered by rule 3 — every id a
stranger tries returns 404 — and nothing in this application creates rows offline.

What integers buy is smaller indexes, readable URLs, and a database whose rows sort by
insertion order for free.

**Cost:** the count of anything is visible to whoever can read one of its ids. If this ever
grew a public surface, that becomes a real objection and the fix is a UUID column beside the
key, not instead of it.

---

## 6. A queue inside the database

**Chosen:** Messenger over the Doctrine transport.

Redis or AMQP would scale further and neither can do the one thing this needs: `dispatch()` on
the Doctrine transport is an `INSERT` on the connection already in a transaction, so a message
is committed or rolled back **with the change it announces**. A result that fails to save never
tells anybody it did.

It also carries the non-transactional side effects. Publishing to the realtime hub is an HTTP
call that cannot be taken back, so it does not happen in the service that changed the data — a
message does, and the publisher runs after the commit.

**Cost:** a queue that polls a table rather than being pushed to, and a worker that has to be
running. At a few thousand messages a day, neither is felt.
