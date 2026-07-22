# 0007 — WordPress Integration Architecture

**Status:** Accepted (Milestone 9)

## Decision

Introduce a dedicated `App\Services\WordPress\` integration layer —
`Contracts`, `Client`, `Authentication`, `DTO`, `Exceptions`,
`Security` — as the *only* code in this application that ever talks to
an external WordPress site. Authenticate against WordPress using its
own built-in Application Passwords (HTTP Basic Auth), never a bearer
token WP Studio invents. Store the credential encrypted, in a separate
table `SiteResource` never touches. Extend the existing `Site` domain
(new columns, a new `SiteConnectionService` alongside the unchanged
`SiteService`) rather than building a parallel resource. Guard every
outbound connection attempt against SSRF. Treat "verify a connection"
and "refresh its metadata" as the same operation over the wire, exposed
as two actions because they mean different things to a user.

## Context

**What this milestone is.** Milestones 6–8 built a `Site` domain that
was metadata-only — real CRUD (M7), real auth and tenant isolation
(M8), but nothing that had ever actually talked to a WordPress
installation. `ADR 0004`'s own Future Implications section named this
milestone's job directly: "the real OAuth/API-key connection flow that
creates `Site` rows from an actual WordPress handshake." This ADR is
that flow.

**Architecture review before implementation.** Reviewing the existing
`CurrentWorkspaceResolver`, `SitePolicy`, `SiteController`/
`SiteService`, and `ApiResponse`/`ApiExceptionHandler` before writing
anything surfaced that every one of them was directly reusable — see
"What's Already There to Extend" in this milestone's own approval
thread. The one genuinely new piece is the HTTP client that talks to
an external, uncontrolled system; everything else is extension, not
replacement.

## Domain Model Changes

**`sites` table** (new migration, not another amendment of the M6/M7
`sites` migration — see that migration's own doc comment for why this
one is a new file): `url`, `php_version`, `plugin_count`, `user_count`,
`timezone`, `language`, `last_connected_at`, `last_checked_at`,
`connection_error`.

**`site_credentials`** (new table): `site_id` (unique FK), `wp_username`,
`application_password` (`encrypted` cast). See Security below for why
this is a separate table, not columns on `sites`.

**`SiteStatus::Error`** (new enum case): a site that *was* connected
but whose most recent verify/refresh failed — distinct from
`Disconnected` (never connected, or deliberately disconnected) so the
UI can show a real error state with `connection_error`, not a neutral
"not connected" one.

## Why Application Passwords

WordPress core has shipped Application Passwords since 5.6 — a
per-application, individually revocable credential (never the
account's real login password), authenticated via plain HTTP Basic
Auth. Rejected alternatives:

- **OAuth2.** Requires a registered client app, a redirect/consent
  flow, and — critically — isn't a WordPress core feature; it would
  mean requiring a third-party plugin on every site a user wants to
  connect, which is a real adoption barrier this project has no reason
  to impose when core WordPress already ships a first-party answer.
- **A custom WP Studio companion plugin issuing its own tokens.** Would
  unlock real capability this milestone explicitly can't get otherwise
  (see Version Detection below) — but building a WordPress plugin is
  real scope beyond "connect to a site," and doing it well means
  designing a plugin update/distribution story this milestone isn't
  scoped for. Named as a real future path (see Future Extensibility),
  not built now.

Application Passwords are the only option requiring **zero** software
installed on the target site — exactly the "connect any WordPress
site" promise this milestone makes.

`App\Services\WordPress\Authentication\ApplicationPasswordAuthenticator`
is a small, separate class specifically so a second auth method later
(a companion plugin's own token scheme) is a new class implementing the
same shape, not a rewrite of `HttpWordPressClient`'s request machinery.

## Service Boundaries

**`SiteConnectionService` is not an extension of `SiteService`.**
`SiteService` is "given already-known attributes, persist them" (thin,
unchanged this milestone — it lost its own `create()` method, which
this service's `connect()` now subsumes with real verification behind
it). `SiteConnectionService` is "given a URL and a credential, go
verify them against a real external system and derive attributes from
what it returns" — network calls, retries, DTO mapping, a materially
different responsibility. This is the same boundary that already
separates `PublishingService` from `SiteService`/`PostService`
(`0005-domain-model.md`) — reused, not reinvented.

**Controllers never talk to WordPress.** `SiteController`'s four
WordPress-facing actions (`store`/connect, `disconnect`,
`verifyConnection`, `refreshMetadata`) all delegate to
`SiteConnectionService`. `HttpWordPressClient` is the only class that
constructs an `Http::` call to an external host.

**One contract method, deliberately.**
`WordPressClientContract::fetchSiteInfo()` is the entire interface —
"verify" and "refresh" are the same fetch over the wire; they only mean
different things at the `SiteConnectionService`/UX layer.
`AppServiceProvider` binds the contract to `HttpWordPressClient`, so a
test double (or a future alternate implementation) never requires
touching a consumer.

## Failure Handling

Every WordPress-integration exception extends
`WordPressIntegrationException` (itself extending the existing
`App\Exceptions\ApiException`), rendering through the same
`ApiExceptionHandler` every other error in this API does — a frontend
consumer never learns a new envelope shape for this milestone.

| Exception | HTTP Status | Meaning |
| --- | --- | --- |
| `WordPressConnectionException` | 503 | Unreachable — DNS, refused, timeout, SSL failure, or an SSRF-unsafe URL |
| `WordPressAuthenticationException` | 422 | Reachable, but the Application Password was rejected |
| `WordPressResponseException` | 502 | Reachable, authenticated, but the response was unusable (not JSON, missing an expected field) |

**Two calls are load-bearing; three are best-effort.** The root index
(`/wp-json/`, proves the URL is a WordPress site) and `/wp/v2/settings`
(proves the credential works — it requires `manage_options`) both
propagate failure. `/wp/v2/themes`, `/wp/v2/plugins`, and
`/wp/v2/users` are each independently capability-gated by WordPress
itself; an Application Password created by a non-administrator can
legitimately authenticate but lack the capability for one or more of
these. `HttpWordPressClient::fetchOptional()` treats that as "this
field isn't available" (`null`), not a failed connection — a
successful connection with partial metadata, not an error. This is the
concrete implementation of "graceful degradation... partial failures"
from this milestone's brief, not an aspiration.

**Timeouts and retries.** 5s connect / 10s total per request, two
retries on transport-level failures only (`ConnectionException` —
DNS/refused/timeout), never on an HTTP-level error status (Laravel's
`retry()` defaults to throwing on any non-2xx after retries exhaust;
explicitly passed `throw: false` to keep that decision in
`fetchRequired()`/`fetchOptional()`, not the retry mechanism — a real
bug caught during this milestone's own test-writing, see the
Engineering Journal).

## Security Model

**Credential storage — four independent layers, not one.**

1. A separate table (`site_credentials`), not columns on `sites` —
   `SiteResource` serializes the `Site` model directly, so a credential
   column on that same table is one accidental `toArray()` change away
   from a leak.
2. The Eloquent `encrypted` cast (AES-256-CBC via `Crypt`, keyed by
   `APP_KEY`) on `application_password`. `wp_username` is left plain —
   it isn't a bearer credential on its own.
3. `$hidden = ['application_password']` on `SiteCredential`.
4. `Site::credential()` is never eager-loaded by anything that
   serializes a response — verified directly in this milestone's own
   tests (`it stores the credential encrypted and never as plaintext`,
   asserting both the decrypted value round-trips correctly *and* the
   raw database column never contains the plaintext).

**SSRF is the first-order risk this feature introduces, not a side
concern.** "Connect to a URL a workspace member supplies" is a
request-forgery primitive without a check — a member could point a
connection attempt at an internal hostname or a cloud metadata endpoint
(`169.254.169.254`) this server can reach but the internet can't.
`App\Services\WordPress\Security\UrlSafetyValidator` rejects non-http(s)
schemes, `localhost`/`.local`/`.internal` hostnames, and any literal IP
address in a private or reserved range (`FILTER_FLAG_NO_PRIV_RANGE` /
`FILTER_FLAG_NO_RES_RANGE`) — checked **before** any request is sent
(verified in tests via `Http::assertNothingSent()`).

~~**Named, accepted residual risk: no DNS resolution check.**~~
**Resolved, Milestone 19** — an injectable `DnsResolver` now resolves
each hostname and checks every address it points at, using the same
private/reserved-range filter the literal-IP path already used. The
network-call-in-tests tension named here was solved with a fake
resolver bound for the whole test suite (the same pattern
`Http::fake()` already established), not by skipping the check. DNS
rebinding (the hostname resolves safely at check-time, differently at
request-time) remains a further, more advanced hardening step, not
currently justified by this app's threat model. See
`docs/adr/0017-cloud-deployment-and-security-hardening.md`.

**Rate limiting.** `connect`/`verify`/`refresh-metadata` all carry a
new `wordpress-connection` limiter (10/minute, keyed by the
authenticated user) — without it, these endpoints are a way to make
this server issue repeated outbound requests to an arbitrary target on
demand, a real abuse vector distinct from (but adjacent to) SSRF.
`disconnect` doesn't call WordPress at all and isn't limited, matching
`update`/`destroy`.

## Tenant Isolation

Unchanged mechanism, new consumer: every WordPress-facing route sits
behind the same `auth:sanctum` + `ResolveCurrentWorkspace` pipeline
`0006-authentication-architecture.md` established. `SiteController`'s
new actions call `$this->authorize()` against the same
`SitePolicy` Milestone 7 wrote:  `disconnect` requires owner/admin
(`update`); `verifyConnection`/`refreshMetadata` require only
membership (`view`) — re-checking an already-stored credential is
read-adjacent, the same posture `SitePolicy::view()` already takes for
plain reads. Verified directly:
`WorkspaceIsolationTest`/`WordPressConnectionTest` assert a member of
one workspace can neither view, disconnect, nor smuggle a post into a
site belonging to another.

## Version Detection — an Honest Accounting

`wordpress_version` and `php_version` are real columns, populated as
`null` by every connection this milestone makes. Not a bug: stock
WordPress deliberately doesn't expose either through its public REST
API (removed as part of WordPress's own version-fingerprinting
hardening), and there is no reliable, generically-available endpoint
that returns them. Getting them for real needs a companion plugin
exposing a custom endpoint — real future scope (see Future
Extensibility), not guessed at here. This is the same discipline
`0005-domain-model.md` applied to the "AI Jobs" table: build what's
real, document what isn't achievable with the stated approach, rather
than fake a value that looks more finished than it is.

Every other `WordPressSiteInfo` field (`activeTheme`, `pluginCount`,
`userCount`, `timezone`, `language`) comes from a real, documented
WordPress REST API response this integration actually calls — `theme`
via `/wp/v2/themes`' `status: "active"` entry, `userCount` via the
`X-WP-Total` response *header* on `/wp/v2/users?per_page=1` (WordPress's
own pagination convention, not a body field — fetching one row instead
of the whole collection just to count it), `timezone`/`language` via
`/wp/v2/settings`.

## Rejected Alternatives

**A raw analytics-style events schema for connection history**
(logging every verify/refresh attempt as its own row). Rejected for
now — `last_checked_at`/`last_connected_at`/`connection_error` on
`Site` itself already answer "is this working, and if not, why," which
is what the UI needs today. A real connection-history/audit table is
plausible future scope once a real usage pattern justifies it, the
same reasoning `0005-domain-model.md` applied to deferring a raw
analytics-events table in favor of `AnalyticsSnapshot`.

**Storing the credential un-encrypted, relying on database-level
access control alone.** Rejected outright — `$hidden` and restricted
DB access are necessary, not sufficient; encryption-at-rest means a
raw database dump or backup doesn't hand over a working credential.

## Future Extensibility

- **A WP Studio companion plugin** is the real unlock for
  `wordpress_version`, `php_version`, and richer capability detection
  — `ApplicationPasswordAuthenticator`'s isolation and
  `WordPressClientContract`'s single-method shape both exist so this is
  additive (a new authenticator, a richer DTO) when it happens, not a
  rewrite.
- **Content Management / Publishing** (future milestones): `Site` now
  has a real `url` and a verified credential — the actual "create a
  post on the real WordPress site" call is `HttpWordPressClient`-shaped
  work, following this same client/DTO/exception pattern rather than a
  new one.
- **Background Jobs**: `verifyConnection`/`refreshMetadata` are
  synchronous today (a user clicks a button, waits for one request).
  The natural evolution — periodic background re-verification of every
  connected site — is a queued job calling
  `SiteConnectionService::verifyConnection()` unchanged; the service
  boundary was drawn with this in mind.
- **DNS-resolution SSRF hardening**: named above, Milestone 19.
