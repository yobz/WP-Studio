# 0012 — AI-Assisted Content Generation

**Status:** Accepted (Milestone 14)

## Decision

Introduce a dedicated `App\Services\AI\` integration layer — `Contracts`,
`Client`, `DTO`, `Exceptions` — as the *only* code in this application that
ever talks to an external AI provider, following the same shape
[[0007-wordpress-integration-architecture]](0007-wordpress-integration-architecture.md)
established. Add the `ai_jobs` table
[[0005-domain-model]](0005-domain-model.md) deliberately deferred until a
real provider existed to design against. Process generation asynchronously
through the Milestone 11 job platform
([[0009-background-job-platform]](0009-background-job-platform.md)) rather
than blocking a request on unpredictable LLM latency. Support **two**
interchangeable providers — Anthropic Claude and Google Gemini — behind one
`AiClientContract`, selected at runtime by `config('ai.provider')`. Wire the
Dashboard's already-built, previously-disabled `AiAssistantPreview` "Generate"
action to this pipeline.

## Context

**What this milestone is.** The last named gap from
[[0005-domain-model]](0005-domain-model.md): "AI Jobs is documented here but
deliberately has no table yet... an AI job's real shape... isn't knowable
without designing against a real provider integration." This milestone is
that real integration. `AiAssistantPreview` has shipped since Milestone 5 as
an honest, deliberately-mocked preview
([[0003-dashboard-data-architecture]](0003-dashboard-data-architecture.md));
this milestone gives its `Generate` button a real backend for the first
time.

**Architecture Drift Review** (standing since Milestone 12). Reviewed the
existing `App\Services\WordPress\` and `App\Services\Media\` integration
layers, the Milestone 11 job platform, and
[[0011-graphql-layer]](0011-graphql-layer.md)'s REST/GraphQL boundary before
writing anything. No duplication or overlapping responsibility found:

- `App\Services\AI\` is genuinely new surface — no existing service talks to
  an external LLM provider.
- `GenerateAiContentJob` reuses the exact job shape `SyncWordPressPostsJob`/
  `RefreshSiteMetadataJob`/`DownloadMediaJob` already established (`tries`,
  `backoff()`, a `failed()` callback that records the failure onto the
  owning row) — not a new pattern.
- Considered whether this belonged on the GraphQL layer instead of REST.
  Rejected: [[0011-graphql-layer]](0011-graphql-layer.md) scoped GraphQL
  deliberately to read-only dashboard aggregation with no mutations; content
  generation is a write/action endpoint, the same shape as every other
  mutating endpoint in this API. Adding it to GraphQL would mean either
  breaking that ADR's own boundary or maintaining two mutation surfaces for
  no benefit.
- Considered extending `PublishingJob` instead of a new `ai_jobs` table.
  Rejected: a `PublishingJob` is "this post is scheduled to go out to
  WordPress" — a fundamentally different lifecycle and owner (`Post`) than
  "this prompt produced this text" (owned by `Workspace`/`User`, no `Post`
  necessarily involved). Reusing the table would mean nullable
  provider/model/token columns on every publishing job and vice versa.

## Domain Model

**`ai_jobs`** (new table): `workspace_id`, `user_id` (both `cascadeOnDelete`
— consistent with every other tenant-scoped table since
[[0005-domain-model]](0005-domain-model.md)), `prompt` (text), `status`
(`AiJobStatus`: `Pending`/`Processing`/`Completed`/`Failed` — the same
generic async-operation-record shape `PublishingJobStatus` already
established), `result` (text, nullable), `error_message` (text, nullable),
`model` (string, nullable — which model actually served the request, since
that can vary by provider and by Gemini's `modelVersion` response field),
`input_tokens`/`output_tokens` (nullable — cost/usage accounting), `attempted_at`/
`completed_at`. Composite index on `(workspace_id, status)`, matching
`posts`' `(site_id, status)` reasoning
([[0005-domain-model]](0005-domain-model.md)) — SQLite doesn't auto-index a
plain foreign key, and this is the query shape a future "my recent
generations" view would actually use.

**Deliberately no `site_id` column.** `AiAssistantPreview` has no site
selector — it is a single prompt box, not a per-site content tool. Adding a
nullable `site_id` now would be schema speculation ahead of a UI that
doesn't exist, the exact anti-pattern
[[0005-domain-model]](0005-domain-model.md) named when it deferred this
table in the first place. A future "generate directly into a post draft on
Site X" feature adds the column when it's actually consumed, not before.

**No `type` column.** Every generation is the same shape — a prompt in, text
out. The Dashboard's suggested-prompts list (draft a post, summarize
recent posts, suggest SEO titles) is UI-level guidance, not a backend
taxonomy; the prompt string itself carries the intent. Adding an enum now
for zero current consumers would be exactly the kind of premature
abstraction this project's standing engineering guidance avoids.

## Provider Abstraction

**`AiClientContract`** — one method, `generate(string $prompt):
AiGenerationResult`, mirroring `WordPressClientContract`'s "one contract
method, deliberately"
([[0007-wordpress-integration-architecture]](0007-wordpress-integration-architecture.md)).
Two implementations:

- **`AnthropicMessagesClient`** — wraps the official `anthropic-ai/sdk`
  PHP package. Model defaults to `claude-opus-4-8`.
- **`GeminiClient`** — raw HTTP via Laravel's `Http` facade against
  `generativelanguage.googleapis.com`'s REST API, following
  `HttpWordPressClient`'s exact request/retry/exception-mapping shape
  (connect/request timeouts, retry-on-`ConnectionException`-only, a private
  `assertSuccessfulResponse()` gate). No official Google PHP SDK was used —
  raw HTTP is this project's own established pattern for a third-party
  integration
  ([[0007-wordpress-integration-architecture]](0007-wordpress-integration-architecture.md)),
  and it avoids depending on an unverified community package for a REST API
  that's simple and stable enough not to need one.

`AppServiceProvider` binds `AiClientContract` via a closure —
`config('ai.provider') === 'gemini' ? GeminiClient::class :
AnthropicMessagesClient::class` — read at resolution time, not boot time, so
a long-running `queue:work` process resolves the contract fresh per job
rather than freezing the provider choice at worker-boot.

**This is a mid-milestone scope change, made explicitly, not silently.**
This milestone was originally scoped to Claude only, per this session's
tooling defaults. The user, mid-implementation, asked to support Gemini as
well, behind a switch — not a replacement. Rather than rewrite the
already-implemented, already-tested `AnthropicMessagesClient`, the existing
`AiClientContract` abstraction (deliberately shaped after
`WordPressClientContract`'s "swap the implementation, not the caller"
precedent) absorbed the second provider as a pure addition: `GeminiClient`,
one `match` arm in the binding closure, one new config block, one new test
file. Nothing about `AiJobService`, `GenerateAiContentJob`,
`AiJobController`, or the frontend needed to change — the contract did
exactly the job it was designed for.

## Async Job Platform

`POST /api/v1/ai/generate` creates an `AiJob` row (`status: pending`),
dispatches `GenerateAiContentJob`, and returns `202 {status: 'queued',
job_id}` immediately — the identical shape and status code
`ContentSyncController::sync()` established
([[0008-content-synchronization]](0008-content-synchronization.md)), chosen
for the same reason: don't imply the mutated resource's fresh state is in
the response when a background worker (not this request) produces it.
`GET /api/v1/ai/jobs/{aiJob}` is the poll endpoint, mirroring
`GET /sites/{site}/sync-status`.

**`GenerateAiContentJob`** — `tries = 3`, `backoff() => [10, 30, 60]`,
identical to `SyncWordPressPostsJob`
([[0009-background-job-platform]](0009-background-job-platform.md)).
Non-retryable failures (`AiResponseException` — malformed/empty response;
`AiConfigurationException` — bad or missing credentials) are caught inside
`handle()` and resolved via `$this->fail($e)` immediately, the same pattern
`SyncWordPressPostsJob` uses for `ContentSyncException`. Retryable failures
(`AiProviderException` — rate limits, connection failures, 5xx) are **not**
caught — they bubble out of `handle()`, so Laravel's queue worker retries
with the configured backoff and only calls `failed()` once `tries` is
exhausted. This distinction was verified for real, not just reasoned about,
during this milestone's own live verification (see Engineering Journal).

**Frontend polling** — `useAiJob(jobId)` polls every 2s while `status` is
`pending`/`processing`, identical to `useSyncStatus`/`useSite`
([[0009-background-job-platform]](0009-background-job-platform.md)).
`useGenerateContent()` is a plain mutation returning the queued job id;
`AiAssistantPreview` holds that id in local state and feeds it to
`useAiJob`, so the widget needs no new global state (no new Zustand store,
consistent with
[[0003-dashboard-data-architecture]](0003-dashboard-data-architecture.md)'s
standing rule that TanStack Query owns server state).

## Failure Handling

Every AI-integration exception extends `AiIntegrationException` (itself
extending `App\Exceptions\ApiException`), rendering through the existing
`ApiExceptionHandler` — no new envelope shape.

| Exception | HTTP Status | Meaning |
| --- | --- | --- |
| `AiProviderException` | 503 | The provider is temporarily unavailable — rate limited, unreachable, or returned a 5xx. Retryable at the job level. |
| `AiResponseException` | 502 | The provider responded successfully but the payload was unusable (empty/missing text). |
| `AiConfigurationException` | 500 | This application's own credential/config is missing or was rejected — never the user's fault. Message is generic to the client (`"AI generation is temporarily unavailable..."`); the real reason is logged server-side and included in the response only when `app.debug` is true. |

Both provider clients map their own SDK/HTTP error shapes onto this same
three-way split — Anthropic's typed SDK exceptions
(`AuthenticationException`/`PermissionDeniedException` → configuration,
`RateLimitException`/`APIConnectionException`/`APIStatusException` →
provider) and Gemini's raw HTTP status codes (401/403 → configuration, 429 →
provider, other failures → provider, non-2xx/malformed body → response) —
so a caller two layers up never needs to know which provider is configured.

## Security

**Rate limiting.** `ai-generation` limiter, 10/minute per authenticated
user — the same posture as `wordpress-connection`/`media-upload`
([[0007-wordpress-integration-architecture]](0007-wordpress-integration-architecture.md)),
necessary here for the same reason: an unlimited endpoint that triggers a
paid, metered external API call on demand is a real abuse/cost vector, not
just a UX nicety.

**Credentials.** `ANTHROPIC_API_KEY`/`GEMINI_API_KEY` are application-level
secrets (one per deployment), not per-tenant credentials — unlike WordPress
Application Passwords
([[0007-wordpress-integration-architecture]](0007-wordpress-integration-architecture.md)'s
four-layer credential-storage model), there is nothing to encrypt-at-rest or
scope per workspace; they live in `.env`/`config/ai.php` exactly like every
other third-party API key this project already has a place for
(`config/services.php`). No new storage concern.

**Prompt/result storage.** `ai_jobs.prompt` and `.result` are stored in
plaintext, workspace-scoped, behind the same `AiJobPolicy` every other
tenant resource uses. This is intentional — a generation history is
useful, and nothing here is more sensitive than a `Post`'s own content,
which is already stored the same way.

## Tenant Isolation

Unchanged mechanism, new consumer: `POST /ai/generate` and
`GET /ai/jobs/{id}` sit behind the same `auth:sanctum` +
`ResolveCurrentWorkspace` pipeline every route does. `AiJobPolicy` (`view`,
`create`) checks `$aiJob->workspace->hasMember($user)` — the same one-line
pattern every policy since
[[0005-domain-model]](0005-domain-model.md) has used. Verified directly:
`AiGenerationTest`'s `cannot view a generation job belonging to another
workspace` asserts a 403, and `lets any workspace member generate content`
confirms creation isn't owner/admin-gated (matching `PostPolicy::create`'s
posture — generating a draft is a content-creation action, not an
ownership-transfer one).

## Rejected Alternatives

**A synchronous request/response instead of a queued job.** LLM generation
latency is unpredictable — seconds to tens of seconds depending on prompt
and provider load — and this project already has a purpose-built async job
platform for exactly this shape of problem
([[0009-background-job-platform]](0009-background-job-platform.md)).
Blocking an HTTP request on an external, variable-latency call would be the
same mistake Milestone 10's synchronous sync endpoint was deliberately
built to be replaced away from.

**Exposing generation as a GraphQL mutation.** Rejected in the Architecture
Drift Review above — see [[0011-graphql-layer]](0011-graphql-layer.md)'s own
"no mutations" scope boundary.

**A community Google Generative AI PHP package for Gemini**, instead of raw
HTTP. Rejected for the same reason `HttpWordPressClient` is hand-rolled
rather than built on a WordPress SDK: the REST surface is small, stable, and
well-documented, and a raw `Http::` client avoids a dependency whose API
shape can't be verified against this session's tooling the way the official
Anthropic SDK could be.

## Live Verification — A Real, Non-Obvious Finding

Live browser verification (see `docs/ENGINEERING_JOURNAL.md`'s dated entry)
surfaced a genuine external-API issue that no amount of code review would
have caught: the Gemini default model this milestone first shipped with
(`gemini-2.5-flash`) returned a live `404` from Google — *"This model...is
no longer available to new users"* — despite being listed as a current
stable model in Google's own documentation fetched the same session. Probing
several model IDs directly against the configured key (via `php artisan
tinker`, never printing the key itself) distinguished this from a
credential problem: `gemini-2.0-flash` and `gemini-2.5-pro` both returned
`429` (rate/quota limited) — which only happens *after* successful
authentication — proving the key was valid and the failure was
model-availability, not auth. The default was switched to
`gemini-2.0-flash`. A full success-path live demo was ultimately blocked by
the account's free-tier daily quota (`generate_content_free_tier_input_token_count`
exhausted), not by anything this milestone's code does — the retry,
error-mapping, and UI-degradation path for that exact failure was verified
live instead, and the success path remains covered by
`AiGenerationTest`'s fake-provider integration test.

## Future Evolution

- **A real generation-history view** (list past `AiJob` rows for a
  workspace) — the `(workspace_id, status)` index was added with this in
  mind; no endpoint exists for it yet because no UI asks for it.
- **Site/post-targeted generation** — "draft directly into a new Post on
  Site X" — the natural next step once a real UI names it; `ai_jobs` gains
  a nullable `post_id`/`site_id` then, not speculatively now.
- **Streaming responses** — both provider SDKs support streaming; the
  synchronous `AiClientContract::generate()` shape would need a second
  method or a different contract to support token-by-token UI updates.
  Deliberately out of scope — the Dashboard widget shows a completed draft,
  not a live-typing effect.
- **A paid-tier or Anthropic key for a full live success-path demo** —
  named directly in Session Handoff; the code path is written and unit/
  integration-tested, just not exercised against a real successful
  response in a live browser this session.
