# Milestone 9 Report

## Date

2026-07-14

---

## Objective

Transform WP Studio from a dashboard application into a real
multi-site WordPress management platform — the integration layer
between Laravel and external WordPress installations, foundational for
future Content Management, Publishing, AI Content Generation,
Analytics, Scheduling, and Background Jobs milestones. A dedicated
`App\Services\WordPress\` layer (Contracts/Client/Authentication/DTO/
Exceptions/Security), Application Password authentication, real
connection lifecycle (connect/disconnect/verify/refresh), and a real
frontend site-management UI. Treat WordPress as an unreliable external
dependency throughout: network failures, invalid credentials, SSL
failures, malformed responses, and — identified during architecture
review, not named in the brief's own failure-mode list — server-side
request forgery.

---

## Executive Summary

Milestone 9 is complete and, on independent review, sound. The
architecture review earned its keep the same way Milestone 8's did:
confirming every existing platform service (`CurrentWorkspaceResolver`,
`ApiResponse`/`ApiExceptionHandler`, `SitePolicy`, auth middleware) was
directly reusable, and identifying SSRF as the actual first-order risk
this feature introduces — before any code existed, not discovered
during a security pass afterward. `UrlSafetyValidator` closes it, with
a test that proves the negative (`Http::assertNothingSent()`), not
just that an error is returned.

The service-boundary decision — a new `SiteConnectionService` rather
than extending `SiteService` — holds up under review: `SiteService`
lost a method (`create()`) and gained no new responsibility;
`SiteConnectionService` owns exactly the new, materially different
work (network calls, retries, DTO mapping). The "two load-bearing
calls, three best-effort" design inside `HttpWordPressClient` is a
genuine, tested implementation of graceful degradation — an
Application Password without full admin capabilities connects
successfully with partial metadata, verified by a dedicated test, not
asserted in a comment.

One real bug was found and fixed during test-writing:
`Http::retry()`'s default `throw: true` was silently converting every
non-2xx WordPress response into a generic `RequestException` before
this integration's own 401/403 → `WordPressAuthenticationException`
mapping ever ran. Caught immediately because the test suite exercises
the actual failure paths, not just the success path — exactly the
value automated coverage of failure modes is supposed to provide.

Backend test coverage grew from 57 to 73 tests, all mocking WordPress
via `Http::fake()` — zero live external dependency. Frontend adds this
app's first dynamic/nested route and, as a direct consequence, closes
a real UX gap (`AppSidebar`'s exact-match highlighting) that had sat
deferred since Milestone 4.1 waiting for exactly this trigger.

---

## Engineering Summary

**Backend.** `App\Services\WordPress\` — `Contracts\WordPressClientContract`
(one method: verify and refresh are the same fetch over the wire),
`Client\HttpWordPressClient` (the only class making outbound HTTP
calls to WordPress), `Authentication\ApplicationPasswordAuthenticator`
(HTTP Basic Auth, isolated for future extensibility),
`DTO\WordPressSiteInfo`, `Exceptions\{WordPressConnectionException,
WordPressAuthenticationException,WordPressResponseException}` (all
extending the existing `ApiException`), `Security\UrlSafetyValidator`
(the SSRF guard). `SiteConnectionService` orchestrates connect/
disconnect/verifyConnection/refreshMetadata. New `site_credentials`
table (encrypted `application_password`, never touched by
`SiteResource`); new `sites` columns (`url`, `php_version`,
`plugin_count`, `user_count`, `timezone`, `language`,
`last_connected_at`, `last_checked_at`, `connection_error`); new
`SiteStatus::Error` case. `StoreSiteRequest`/`UpdateSiteRequest`
contracts changed deliberately (flagged in the architecture review
before implementing) — WordPress-derived fields are no longer
client-settable at all.

**Frontend.** New `dialog.tsx` primitive (hand-extracted via `--view`,
not `shadcn add`, protecting the hardened `Button` — same process as
Milestone 4's `sidebar`/`sheet`, including fixing the same
accessible-name gap in the generated close button).
`src/features/wordpress/` — `ConnectSiteDialog` (React Hook Form +
Zod), `SitesList`, `SiteDetail`, five TanStack Query hooks. `/wordpress`
is real now; `/wordpress/[id]` is this app's first dynamic route.
`AppSidebar`'s `isActive` now matches a path-segment prefix.

---

## Security Summary

- **SSRF.** `UrlSafetyValidator` rejects non-http(s) schemes, local
  hostnames (`localhost`, `.local`, `.internal` suffixes), and literal
  private/reserved IP addresses, checked *before* any request is sent.
  Verified directly: two tests assert `Http::assertNothingSent()` for
  a private-IP and a `localhost` connection attempt — proving the
  guard runs ahead of the network call, not just that the eventual
  response is an error.
- **Residual, named risk:** no DNS resolution — a hostname that
  resolves to a private IP isn't caught. Deliberate (see the ADR),
  deferred to Milestone 19, not overlooked.
- **Credential storage — four layers, all independently verified.** A
  separate table (`site_credentials`), the `encrypted` Eloquent cast,
  `$hidden`, and `SiteResource` never eager-loading the relationship.
  `it stores the credential encrypted and never as plaintext` asserts
  both that the value round-trips correctly through decryption *and*
  that the raw database column never contains the plaintext string —
  not just one or the other.
- **Rate limiting.** `wordpress-connection` (10/min per user) on
  `connect`/`verify`/`refresh-metadata` — verified by test (10 attempts
  succeed as expected failures, the 11th is 429).
- **Tenant isolation.** Every new action reuses the existing
  `auth:sanctum` + `ResolveCurrentWorkspace` + `SitePolicy` pipeline.
  `WordPressConnectionTest` includes a dedicated cross-workspace
  disconnect-rejection case, matching the same isolation discipline
  Milestone 8 established.
- **Never trusting client-supplied WordPress metadata.** The single
  biggest integrity fix this milestone makes: before, a client could
  `POST /sites` with any `wordpress_version`/`theme`/
  `plugin_updates_available` it wanted. Now those fields are
  exclusively server-derived from a verified handshake. Named and
  approved explicitly in the architecture review as Decision 1, not
  slipped in silently.

---

## Architecture Summary

Independently re-assessed against the milestone's own stated goal
("favor extending existing platform services over introducing parallel
implementations"): every reused piece (`CurrentWorkspaceResolver`,
`ApiResponse`, `SitePolicy`, TanStack Query, `api-client.ts`) is used
unchanged. The one new abstraction layer (`App\Services\WordPress\`)
is justified — nothing in this codebase talked to an external HTTP
service before this milestone, so there was no existing abstraction to
extend for that specific concern. The `docs/AI_ENGINEERING_CONTEXT.md`
update explicitly frames this layer as the *template* for the next
external integration (a future AI provider, future storage backend),
which is the right level of reuse to aim for — not "WordPress-specific
code," but "the shape any external integration in this codebase should
follow."

The `SiteConnectionService`/`SiteService` split was the one place worth
scrutinizing for over-engineering — two services touching the same
model is a real smell if the boundary isn't load-bearing. It is here:
`SiteService::update()` is a plain, synchronous, no-external-dependency
attribute write; every `SiteConnectionService` method makes (or
depends on the result of) a network call. A future engineer extending
`SiteService::update()` should never need to think about HTTP
timeouts; a future engineer extending `SiteConnectionService` should
never need to think about which plain attributes are user-editable.
That's a real boundary, not a nominal one.

---

## Accessibility Summary

Not independently re-audited with `axe-core` this milestone (no
change to established primitives beyond the new `dialog.tsx`, which
directly reused the accessible-name fix pattern already proven correct
for `SidebarTrigger`/`SheetContent` in Milestone 4). The one
pre-existing, deferred `region` landmark finding from Milestone 8
(portaled `DropdownMenu`/`Popover` content) is unchanged and remains
tracked in the Future Backlog — `Dialog` uses the same portal
mechanism and would carry the same characteristic if scanned with the
dialog open, consistent with the existing finding rather than a new
one. Recommend a full `axe-core` pass (including the Connect Site
dialog open) at the start of Milestone 10 rather than deferring
indefinitely.

---

## Technical Debt

New this milestone (see `docs/ENGINEERING_JOURNAL.md`'s Future
Backlog for full entries):

- SSRF guard has no DNS-resolution check — Medium, deferred to
  Milestone 19.
- `wordpress_version`/`php_version` always `null` — Medium, deferred
  pending a future companion-plugin integration.
- No `axe-core` pass on the new `/wordpress`/`/wordpress/[id]` pages or
  the Connect Site dialog this milestone — flagged above, recommend
  closing early in Milestone 10.

Resolved this milestone:

- Sidebar `isActive` exact-match gap (deferred since Milestone 4.1).
- `Http::retry()`'s default-throw behavior masking custom exception
  handling — a real bug, not pre-existing debt, caught and fixed
  within this milestone.

---

## Production Engineering Review

| Layer | What changed | What was deferred | Future considerations |
| --- | --- | --- | --- |
| Frontend | New `/wordpress` and `/wordpress/[id]` (first dynamic route), new `Dialog` primitive | Real-time connection status (polling/websocket) | Manual verify/refresh is sufficient at today's usage; revisit if Background Jobs adds periodic re-verification |
| Backend/API | 4 new `SiteController` actions, new WordPress integration layer | Bulk site operations | — |
| Database | `sites` metadata columns, new `site_credentials` table | Connection-history/audit table | Deferred per the ADR's Rejected Alternatives — revisit if a real usage pattern justifies it |
| Authentication | Unchanged | — | — |
| Authorization | New actions wired to existing `SitePolicy`, unchanged logic | — | — |
| External Integrations | First one — the template for future AI/storage integrations | Real version/PHP-version detection | Needs a companion plugin; named, not guessed at |
| Security | SSRF guard, encrypted credentials, rate limiting | DNS-resolution SSRF hardening | Milestone 19 |
| Performance | 5s connect / 10s request timeout, 2 retries on transport failures only | Background/async connection checks | Synchronous today; service boundary already supports moving to a queued job later |
| Observability | Existing `AssignRequestId`/`ApiExceptionHandler` cover new routes automatically | Structured logging of connection attempts | `connection_error` on `Site` is today's only record; a dedicated log/event is future scope |
| Logging | Unchanged | — | — |
| Scalability | No change at today's scale | Rate-limiting tuning under real multi-tenant load | Revisit thresholds once real usage data exists |
| Developer Experience | `fakeSuccessfulWordPressConnection()` and siblings in `tests/Pest.php` — reusable WordPress-fake helpers for any future test | — | — |

---

## Validation

- `npm run typecheck`: pass.
- `npm run lint`: pass.
- `npm run build`: pass — 11 routes, `/wordpress` (3.91 kB) and
  `/wordpress/[id]` (3.78 kB, server-rendered on demand) both new.
- `php artisan test`: **73/73 passing** (194 assertions) — 57 from
  Milestones 6–8 (all still passing unchanged) + 16 new
  (`WordPressConnectionTest`).
- End-to-end browser verification (production build, real network
  access — not mocked): login, sidebar navigation to `/wordpress`,
  seeded connected sites listed, navigation into the nested detail
  route with sidebar prefix-match highlighting confirmed, real seeded
  metadata rendered, a verify-connection attempt against a
  known-dummy credential completing without a crash, Connect Site
  dialog client-side validation, a **real** SSRF rejection against
  `192.168.1.1` (the actual `UrlSafetyValidator` running against a
  genuine request attempt, zero mocking), and a **real** rejection
  connecting to `example.com` (a genuinely reachable, non-WordPress
  site) — 8/8 checks passed, zero unexpected console errors.
- Tenant isolation, secure credential handling, and connection
  lifecycle all covered by dedicated backend tests (see Security
  Summary above) rather than asserted only in prose.
- No routes, API contracts, or database schema broken beyond the
  intentional, flagged `StoreSiteRequest`/`UpdateSiteRequest` contract
  change (Decision 1, approved in the architecture review).

---

## Self Review

**Architectural issues found:** none blocking. The
`SiteConnectionService`/`SiteService` split was scrutinized
specifically for whether it was justified or premature abstraction —
concluded justified (see Architecture Summary).

**Security concerns found and closed within this milestone:** SSRF
(closed, tested), the `Http::retry()` exception-masking bug (closed,
tested), client-settable WordPress metadata (closed by design, per
Decision 1).

**Maintainability risks:** low. The five-file WordPress integration
layer is small enough to read in full in one sitting, and the
Contracts/DTO boundary means a future contributor extending it (a real
version-detection endpoint, a second auth method) has an obvious seam
to extend rather than a monolith to untangle.

**Performance risks:** none at today's scale. The two-load-bearing/
three-best-effort call pattern means a slow or partially-broken
WordPress site costs at most 5 requests × ~10s timeout each in the
worst case (a real, bounded number, not unbounded retry behavior) —
worth revisiting only if a real workspace connects dozens of sites
simultaneously, which nothing in this milestone's scope does.

**Production readiness:** the WordPress integration itself is
genuinely production-shaped (real timeouts, real retries, real SSRF
defense, real encrypted storage) — the gaps that remain
(`wordpress_version`/`php_version` detection, DNS-resolution SSRF
hardening) are named, bounded, and don't block the feature from being
real and safe to use, only from being maximally complete.

---

## Final Verdict

**Approved.** The architecture review's two concrete payoffs this
milestone — confirming reuse over duplication, and naming SSRF as the
headline risk before writing code — are exactly what that stage of the
lifecycle is for. The one real bug found (`Http::retry()`'s default
throw behavior) was caught by the test suite doing its job, not by
manual inspection, which is the intended outcome of writing failure-
path tests rather than only happy-path ones. No blocking issues. Ready
to commit.

Recommended next steps before Milestone 10: commit this milestone's
work (see `docs/SESSION_HANDOFF.md` for the recommended M8/M9 commit
split), run a full `axe-core` pass on the new pages (flagged above as
not yet done), then begin Milestone 10 (API Completion & Frontend
Migration) per `docs/ROADMAP.md`'s v0.8 release — only after explicit
approval, per this milestone's own stop condition.
