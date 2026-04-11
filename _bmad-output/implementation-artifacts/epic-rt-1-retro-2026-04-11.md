# Retrospective — Epic RT-1: Notifications Temps Réel via Reverb/Echo

**Date:** 2026-04-11
**Epic:** RT-1 (Notifications Temps Réel via Reverb/Echo)
**Facilitator:** Bob (Scrum Master)
**Participants:** Amakira (Project Lead), Alice (PO), Charlie (Senior Dev), Dana (QA)

---

## Epic RT-1 Summary

| Metric | Value |
|--------|-------|
| Stories completed | 4/4 (100%) |
| Dev agents | Claude Opus 4.6 (RT-1.1, 1.2, 1.3), GPT-5 Codex (RT-1.4) |
| Code reviews | 4 (3 rounds for RT-1.4) |
| Tests added | ~65 (7 backend + ~58 frontend) |
| Review patches applied | 8 |
| Review items deferred | 10 |
| Production incidents | 0 |

### Stories

| Story | Description | Agent | Review Patches |
|-------|-------------|-------|----------------|
| RT-1.1 | Backend NotificationCreated event + NotificationObserver | Opus 4.6 | 2 |
| RT-1.2 | Pinia notification store + Echo subscription + auth integration | Opus 4.6 | 3 |
| RT-1.3 | NotificationBell + Dropdown consume store, polling removed | Opus 4.6 | 0 (2 deferred) |
| RT-1.4 | Fallback: focus refetch, reconnect, safety poll, 401 cleanup | GPT-5 Codex | 1 |

---

## What Went Well

1. **Clean sequential dependency chain** — Each story built cleanly on the previous (RT-1.1 -> 1.2 -> 1.3 -> 1.4). No backtracking or modifications to prior stories.

2. **Code reviews caught real issues early** — RT-1.1: payload mismatch + weak afterCommit tests. RT-1.2: subscription state stuck + markAsRead rollback corruption. RT-1.4: catch-block cleanup leak. All fixed before shipping.

3. **Deferred items resolved across stories** — RT-1.2 deferred "401 interceptor doesn't call unsubscribe" -> RT-1.4 fixed it. RT-1.3 deferred "unreadCount reconciliation after polling removal" -> RT-1.4's three fallback layers address it.

4. **Infrastructure reuse** — Echo/Reverb already configured for booking chat. No new tech introduced.

5. **Strong test coverage** — Every story shipped with targeted tests. 51 notification tests passing at epic completion.

---

## Challenges

1. **Story creation agent (Opus) inconsistently reliable** — The create-story agent produced specs that were too abstract, missing concrete file paths, or making assumptions without verifying the codebase. Amakira had to rework stories with a different agent before they were solid enough for implementation. This is a reliability problem, not a capability or prompt problem.

2. **Pre-existing test failures blocked full regression** — 71 failures across 16 unrelated files meant RT-1.4 could only verify targeted tests, not full frontend regression.

3. **Multi-agent handoff** — RT-1.4 implemented by GPT-5 Codex (vs Opus for 1.1-1.3) required 3 review rounds to reach the same confidence level. Different agents have different blind spots.

4. **Accumulated deferred tech debt** — 6 items deferred across 4 code reviews, all individually justified but collectively representing risk areas.

---

## Key Insights

1. **The model is capable but unreliable — story quality needs a verification gate, not a better prompt.** The same model that produces excellent specs sometimes produces weak ones. A systematic review pass before dev is needed.

2. **A broken test baseline silently degrades every code review.** Without full regression, code reviews rely on targeted tests only, which can miss cross-cutting regressions.

3. **Sequential story dependencies work well when cleanly scoped.** The RT-1.1 -> 1.2 -> 1.3 -> 1.4 chain had zero backtracking.

4. **Per-story code review before starting the next story is effective.** Bugs caught in review don't compound into later stories.

---

## Previous Retro Follow-Through (Sprint 8 / FIX-18)

| Action Item | Status |
|---|---|
| Backfill FIX-16.1 wallet_transactions descriptions | :x: Not addressed |
| Audit notification URLs backend -> frontend routes | :hourglass: Partially addressed (RT touched notification infra but no explicit URL audit) |

---

## Action Items

### Process Improvements

1. **Add a story validation gate before implementation**
   - Owner: Amakira
   - Success criteria: After create-story runs, systematically review output with a second pass before marking ready-for-dev. No story goes to dev without verified file paths, references, and dev notes grounded in the actual codebase.

2. **Fix the pre-existing frontend test baseline**
   - Owner: Amakira
   - Success criteria: `npm run test:frontend` passes fully — 0 failures. Unblocks full regression verification for all future stories.

### Technical Debt (documented in deferred-work.md)

| Item | Source | Priority |
|------|--------|----------|
| No debounce on window focus handler | RT-1.4 review | Low |
| knownIds race on dropdown first fetch | RT-1.2 review | Low |
| unsubscribe() reads userId from auth store (fragile ordering) | RT-1.2 review | Low |
| 401 Promise.all no .catch() | RT-1.4 review | Low |
| In-flight fetches can write stale state after logout | RT-1.4 review | Low |
| 401 import chain rejection skips clearAuth | RT-1.4 review | Low |

### Carried Over from Sprint 8

| Item | Status |
|------|--------|
| Backfill FIX-16.1 wallet_transactions descriptions | Still pending |
| Audit notification URLs backend -> frontend routes | Still pending |

---

## Deployment Status

**Not yet deployed.** Amakira will fix the test baseline first, run full regression, then deploy RT-1 with the accumulated branch changes.

Sequence: Fix tests -> Full regression green -> Deploy -> Plan next work

---

## Next Epic

No next epic defined. The RT-1 epic completes the realtime notifications feature. Next work will be determined after deployment.
