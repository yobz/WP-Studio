# Session Handoff

Where the project stands right now. Overwritten at the end of every
session — this is a snapshot, not a history (see `docs/DEVLOG.md` for
that). If you're starting a new session, read this first.

## 2026-07-16 — End of Milestone 14 (AI-Assisted Content Generation)

**Milestone state.** Milestone 14 is implemented, validated, and
documented — see `docs/MILESTONE_REPORT_M14.md` for the full
independent review. `docs/ROADMAP.md` marks it complete. Committed and
pushed — nothing outstanding from this milestone's own work.

**New: real AI content generation, two providers, one contract.**
`POST /api/v1/ai/generate` → `GET /api/v1/ai/jobs/{id}` (poll), async
via the Milestone 11 job platform. `App\Services\AI\AiClientContract`
has two implementations — `AnthropicMessagesClient` (official
`anthropic-ai/sdk`, model `claude-opus-4-8`) and `GeminiClient` (raw
HTTP against Google's Generative Language API) — selected by
`AI_PROVIDER` (`anthropic` default, or `gemini`) in `backend/.env`.
`AiAssistantPreview` on the Dashboard is wired to this for real now.

**Three gotchas worth knowing before touching this again.**

1. **`queue:work` doesn't re-read `.env` mid-run.** It boots the
   framework once and reuses that config snapshot for every job it
   processes. If you change `AI_PROVIDER`/`ANTHROPIC_API_KEY`/
   `GEMINI_API_KEY` (or anything in `.env`) while a worker is already
   running, restart the specific worker process — find it via
   `Get-CimInstance Win32_Process -Filter "Name='php.exe'"` and match
   the full command line (`queue:work`), never a blanket
   `taskkill /IM php.exe`, since other unrelated `php.exe` processes
   are very likely also running.
2. **`GEMINI_MODEL` defaults to `gemini-2.0-flash`, not
   `gemini-2.5-flash`.** The 2.5 flash/flash-lite models returned a
   live `404` ("no longer available to new users") against the key
   used during this milestone's verification, despite being listed
   current in Google's own docs. If Gemini generation ever starts
   failing with a 404, check model availability for the configured key
   directly (`php artisan tinker`, probe a few model IDs, read the
   actual response body — a `429` on a probe proves the key is valid,
   a `404` names the real problem) before assuming the key is bad. Full
   account in `docs/ENGINEERING_JOURNAL.md`'s 2026-07-16 entry.
3. **No live-verified successful generation exists yet, for either
   provider.** The Gemini account used for verification hit its
   free-tier daily quota after confirming everything except the
   `Completed` render path live. The next session with a working key
   (paid-tier Gemini, or any Anthropic key) should do one real
   end-to-end generation in a live browser and update this note —
   see `docs/adr/0012-ai-content-generation.md`'s "Live Verification"
   section and its Risks in `docs/MILESTONE_REPORT_M14.md`.

**Immediate next step.** Milestone 15 (Frontend Testing — Vitest +
React Testing Library) is next per `docs/ROADMAP.md` — but is
**explicitly not started**, waiting for approval per the milestone
lifecycle's standing rule.

**Known live gotchas.**
- Same PHP built-in server single-threading caveat noted since
  Milestone 8; expect two or three `php.exe` processes if a queue
  worker is also running — check
  `tasklist /FI "IMAGENAME eq php.exe" /V` (or, for full command
  lines, `Get-CimInstance Win32_Process -Filter "Name='php.exe'"`)
  before assuming something is stuck.
- Verify browser-driven UI flows against a production build
  (`npm run build && npm run start`), not `npm run dev` — a
  Milestone 13 session hit stale/misleading behavior in dev mode that
  the production build didn't reproduce.
- Next.js client-side (App Router) navigation with Playwright needs
  `page.goto()` or a manual URL-polling helper, not a `locator.click()`
  + `page.waitForURL()` combination — documented since Milestone 11.
- When stopping ad hoc dev servers/workers started during
  verification, identify each one's **specific PID** (`netstat -ano |
  grep LISTENING` for web servers; `Get-CimInstance Win32_Process
  -Filter "Name='php.exe'"` for full command-line matching on anything
  without a listening port) and kill only those PIDs. Never
  `taskkill /IM php.exe` or similar.
- `axe-core` is a real transitive dependency (`eslint-plugin-jsx-a11y`
  needs it), not just ad hoc verification tooling — never delete it
  during cleanup. `playwright` is installed with `--no-save` and
  uninstalled again at the end of verification each time it's used.
- Never print any part of an API key/credential (not even length or a
  prefix) into tool output or logs — this project's sandbox rules
  block it outright. To debug a credential issue, check boolean
  presence (`config('...') ? 'yes' : 'no'`) or read the *external
  service's* response body/status code instead.
- Demo login: `test@example.com` / `password`
  (`backend/database/seeders/DatabaseSeeder.php` + `UserFactory`'s
  default).

**Validation status as of this session.** Backend: `php artisan test`
— **142/142 passing** (up from 127). `./vendor/bin/pint --dirty`:
clean. Frontend: `typecheck`, `lint`, `build` all pass. Live
verification: confirmed live against the real Gemini API up through
authentication, request construction, async job processing, retry/
backoff on a real 429, typed error mapping, frontend polling, and a
clean accessible error UI (zero console errors, zero `axe-core`
violations) — blocked from a full `Completed`-state demo by the
connected account's free-tier daily quota, not by any code defect. See
`docs/MILESTONE_REPORT_M14.md`.
