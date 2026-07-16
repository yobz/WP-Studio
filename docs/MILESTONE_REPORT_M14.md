# Milestone 14 Report

## Date

2026-07-16

---

## Objective

Deliver the first real AI provider integration — the last named gap from
`docs/adr/0005-domain-model.md`, which deliberately deferred an "AI Jobs"
schema until a real provider integration existed to design it against.
Connect `AiAssistantPreview`'s already-built, previously-disabled
`Generate` action to a real backend, per `docs/ROADMAP.md`'s own Milestone
14 entry.

---

## Executive Summary

Milestone 14 is complete, with one significant, explicitly-approved
scope change mid-implementation: the milestone was originally scoped to a
single AI provider (Anthropic Claude), per this session's tooling
defaults. Partway through implementation, the user asked for a second,
interchangeable provider (Google Gemini), selected by configuration — not
a replacement. Rather than rework the plan, the already-designed
`AiClientContract` abstraction (deliberately shaped after
`WordPressClientContract`'s "one contract method" precedent from
Milestone 9) absorbed the change as a pure addition: one new class
(`GeminiClient`), one new config block, one new test file, zero changes
to `AiJobService`, `GenerateAiContentJob`, `AiJobController`, or any
frontend code. This is documented as a real, load-bearing validation of
that architectural choice, not a footnote — see
`docs/adr/0012-ai-content-generation.md`'s "Provider Abstraction" section.

The `ai_jobs` table now exists — `docs/adr/0005-domain-model.md`'s
deferred schema, designed for real against two working provider
integrations instead of guessed at. Generation is processed
asynchronously through the exact job-platform shape Milestone 11
established (`GenerateAiContentJob`: `tries: 3`, `backoff: [10, 30, 60]`,
the same numbers `SyncWordPressPostsJob` uses), not a new mechanism.
`AiAssistantPreview` — a prompt textarea and suggested-prompt chips
shipped inert since Milestone 5 — now has real Generating/Completed/Failed
states.

**Live verification against the real Gemini API surfaced a genuine
external-service finding, not a code defect**, and reached a
well-documented, deliberate stopping point rather than a false "done":
the model this milestone first shipped with (`gemini-2.5-flash`) turned
out to be deprecated for new API keys, confirmed directly from Google's
own error response rather than assumed; a full success-path live
demonstration was then blocked by the connected account's free-tier daily
quota — not by anything this milestone's code does. The entire pipeline
up to that point (authentication, request construction, async job
processing, retry/backoff, typed error mapping, frontend polling, and a
clean error UI) was verified live against the real external API, with
zero console errors and zero `axe-core` violations. The successful-
completion render path remains covered by an automated integration test
using a fake provider. Full account of the investigation in
`docs/ENGINEERING_JOURNAL.md`'s dated entry and
`docs/adr/0012-ai-content-generation.md`'s "Live Verification" section —
documented explicitly per the user's own instruction, rather than glossed
over.

---

## Architecture Review

Read `docs/adr/0005-domain-model.md` (the deferred AI Jobs schema and its
stated reasoning), `docs/adr/0007-wordpress-integration-architecture.md`
and `docs/adr/0009-background-job-platform.md` (the two precedents this
milestone's design follows most directly — external-integration-layer
shape and async job shape, respectively), `docs/adr/0011-graphql-layer.md`
(to confirm this belonged on REST, not GraphQL), and the existing
`AiAssistantPreview` component and `AiController` placeholder, before
writing any code. The Claude API skill loaded for this session set the
default model (`claude-opus-4-8`) and SDK usage patterns for the
Anthropic side; Gemini's REST shape was verified against Google's live
API documentation rather than assumed, since no equivalent bundled
reference existed for it.

---

## Architecture Drift Review

**No duplicate services or overlapping responsibility.** `App\Services\AI\`
is genuinely new surface — no existing service talks to an external LLM
provider. `GenerateAiContentJob` reuses `SyncWordPressPostsJob`'s exact
job shape (`tries`, `backoff()`, a `failed()` callback recording the
failure onto the owning row), not a new pattern.

**Scope held against two real pressures to expand it.** Considered and
rejected exposing generation as a GraphQL mutation — `docs/adr/0011-graphql-layer.md`
scoped GraphQL deliberately to read-only aggregation with no mutations;
this stays REST like every other write endpoint. Considered and rejected
extending `PublishingJob` instead of adding a new `ai_jobs` table — a
publishing job and a generation job have different owners (`Post` vs.
`Workspace`/`User`) and different lifecycles; reusing the table would mean
nullable provider/model/token columns on every publishing job and vice
versa.

**Result:** implementation matched the reviewed scope — one integration
layer, one new table, zero changes to any existing REST controller,
route, or GraphQL resolver.

---

## Domain Model & API Design

```
ai_jobs
  workspace_id, user_id (both cascadeOnDelete)
  prompt (text)
  status (AiJobStatus: Pending/Processing/Completed/Failed)
  result (text, nullable)
  error_message (text, nullable)
  model (string, nullable — which model actually served the request)
  input_tokens, output_tokens (nullable — cost/usage accounting)
  attempted_at, completed_at
  index (workspace_id, status)

POST /api/v1/ai/generate   → 202 {status: "queued", job_id}
GET  /api/v1/ai/jobs/{id}  → AiJobResource (poll endpoint)
```

Deliberately **no `site_id` column** — `AiAssistantPreview` has no site
selector; adding one now would be schema speculation ahead of a UI that
doesn't exist, the same discipline `docs/adr/0005-domain-model.md`
originally applied to deferring this table. Deliberately **no `type`
column** — every generation is the same prompt-in/text-out shape; the
suggested-prompts list is UI guidance, not a backend taxonomy.

`App\Services\AI\AiClientContract` — one method, `generate(string
$prompt): AiGenerationResult`. Two implementations: `AnthropicMessagesClient`
(official `anthropic-ai/sdk`, `claude-opus-4-8`) and `GeminiClient` (raw
HTTP via Laravel's `Http` facade against Google's Generative Language
API, following `HttpWordPressClient`'s exact request/retry/exception-
mapping shape). `AppServiceProvider` binds whichever `config('ai.provider')`
names, resolved at container-resolution time (not boot time), so a
long-running `queue:work` process picks up the correct provider per job.

Three typed exceptions cover both providers: `AiProviderException` (503,
retryable — rate limits, connection failures, 5xx), `AiResponseException`
(502 — malformed/empty response), `AiConfigurationException` (500 — this
app's own missing/rejected credential). `GenerateAiContentJob` catches
only the two non-retryable exceptions inline (`$this->fail()` immediately);
`AiProviderException` bubbles out of `handle()` so Laravel's own queue
retry/backoff handles it — verified directly via a live 429 response
during this milestone's own verification, not just reasoned about.

---

## Frontend Integration

`src/services/api/ai.service.ts` (`generateContent`, `getAiJob`) +
`src/features/dashboard/hooks/use-generate-content.ts` (mutation) +
`use-ai-job.ts` (poll query, 2s interval while pending/processing —
identical mechanism to `useSyncStatus`/`useSite`). `AiAssistantPreview`
holds the in-flight job id in local `useState`; no new Zustand store, per
`docs/adr/0003-dashboard-data-architecture.md`'s standing "TanStack Query
owns server state" rule. Three new UI states layered onto the existing
idle state: Generating (spinner, disabled inputs), Completed (result
panel, "New prompt" reset), Failed (inline destructive-styled error with
the underlying message, Generate button re-enabled for retry).

---

## Validation

- `php artisan test`: **142/142 passing** (up from 127) — 7 tests in
  `AiGenerationTest.php` (authentication, prompt validation including a
  whitespace-only-prompt edge case found during self-review, successful
  generation via a fake provider, non-retryable failure handling, a live
  retryable-failure path returning 503 with the job still marked Failed,
  cross-workspace isolation, non-owner generation) and 7 in
  `GeminiClientTest.php` (real HTTP-shape tests against `Http::fake()` —
  success, missing/rejected credentials, rate limiting, unreachability,
  malformed response, and the provider-selection binding itself).
- `./vendor/bin/pint --dirty`: clean.
- `npm run typecheck` / `npm run lint` / `npm run build`: all pass.
- Live browser verification (`php artisan serve` + `queue:work` + `npm
  run start` production build): logged in, submitted a real prompt through
  `AiAssistantPreview`, confirmed the full async pipeline against the real
  configured Gemini API — job creation, queue processing, the actual
  outbound HTTPS call, retry with the exact `[10, 30, 60]` backoff on a
  live `429`, `failed()` correctly marking the job, frontend polling
  picking up the final state, and a clean, accessible error render. Zero
  console errors, zero `axe-core` violations. System Health's existing
  `backgroundQueue` widget correctly reflected the failed job in real
  time with no code changes needed. A full success-path (`Completed`)
  live render was not reached — see Executive Summary and
  `docs/adr/0012-ai-content-generation.md`.

---

## Production Readiness

The integration is additive: no existing route, controller, job, or
frontend hook was modified except the placeholder `AiController` (removed
entirely, replaced by `AiJobController`) and `AiAssistantPreview` itself
(its own previously-inert `Generate` button). Both provider clients
respect existing timeout/retry conventions and never log or expose
credentials. `ai-generation` rate limiting (10/minute/user) protects
against runaway cost on a metered external API, matching the
`wordpress-connection`/`media-upload` precedent. The one real operational
gap: this milestone's own live verification could not confirm a
successful end-to-end generation against a real provider account with
available quota — a real, named risk for a first production deployment,
not a code-quality gap.

---

## Technical Debt Resolved

- **The "AI Jobs" schema `docs/adr/0005-domain-model.md` deferred since
  Milestone 7** — resolved, designed against two real provider
  integrations.
- **`AiAssistantPreview`'s inert `Generate` button, disabled since
  Milestone 5** — resolved.
- **The placeholder `AiController`/`GET /api/v1/ai` endpoint** — removed
  entirely, replaced by real `AiJobController` actions.

---

## Deferred Work

- **A generation-history UI** — `ai_jobs` rows persist (with the
  `(workspace_id, status)` index built for exactly this), but no endpoint
  or UI lists past generations. No current UI asks for it.
- **Site/post-targeted generation** ("draft directly into a Post on Site
  X") — the natural next step once a real UI names it; deliberately not
  built ahead of that UI (see Domain Model above).
- **Streaming responses** — both provider SDKs support it;
  `AiClientContract::generate()` is request/response only, matching the
  Dashboard widget's "show a completed draft" UX, not a live-typing one.
- **A full live success-path demonstration** — blocked by the connected
  Gemini account's free-tier daily quota, not by this milestone's code.
  Revisit with a paid-tier Gemini key or a real Anthropic key.

---

## Risks

- **Two external AI provider dependencies now exist** — a real, ongoing
  cost (two SDKs/HTTP shapes to maintain instead of one), accepted
  deliberately per the user's explicit request; the `AiClientContract`
  abstraction is what keeps this cost bounded to the client layer rather
  than spreading through the codebase.
- **`GEMINI_MODEL`'s default (`gemini-2.0-flash`) was chosen under live
  quota pressure**, not a considered product decision about
  cost/quality/latency trade-offs between Gemini's available models —
  worth a deliberate revisit once a working key with real quota is
  available for comparison.
- **No live-verified successful generation exists for either provider in
  this project's history yet** — the completed-state UI and the
  successful-generation backend path are both covered only by tests using
  a fake provider. A first real production use should treat "does a real
  key actually produce a real completion end-to-end" as an open question
  to verify directly, not an assumption inherited from this milestone.

---

## Recommendation for Milestone 15

Per `docs/ROADMAP.md`, Milestone 15 (Frontend Testing — Vitest + React
Testing Library) is next in sequence, closing the asymmetry flagged in
every milestone review since M5: the backend now has 142 Pest tests, the
frontend has zero automated tests. Waiting for explicit approval before
starting, per this milestone's own stop condition.
