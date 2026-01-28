# Epic 7 Retrospective - Messaging System

**Date:** 2026-01-28
**Facilitator:** Bob (SM Agent)
**Participant:** Lamakira

---

## Epic Overview

| Metric | Value |
|--------|-------|
| Epic | 7 - Messaging System |
| Total Stories | 7 |
| Completed | 7/7 (100%) |
| Status | Done |

### Stories Completed

1. ✅ 7-1: Create Messaging Database Schema
2. ✅ 7-2: Conditional Chat Unlock
3. ✅ 7-3: Face Send Message to Producer
4. ✅ 7-4: Producer Send Message to Face
5. ✅ 7-5: View Conversation History
6. ✅ 7-6: Manual Message Refresh
7. ✅ 7-7: Conversations List View

---

## What Went Well

### 1. Clean Architecture & Code Reuse
- **ConversationPolicy** created in 7-2 was reused seamlessly by both Face (7-3) and Producer (7-4)
- **MessageResource** and **ConversationResource** shared between both user types
- Created `EnsureUserIsProducer` middleware during 7-7 review, mirroring `EnsureUserIsFace` pattern

### 2. Comprehensive Test Coverage
- **131+ messaging-specific tests** across all stories
- Total backend test suite grew from 620 → 774 tests (+154 tests)
- Every story had thorough AC coverage with authorization tests

### 3. Consistent Code Review Process
- All 7 stories went through adversarial code review
- Issues found and fixed before merging:
  - 7-1: 2 MEDIUM (unreadCountFor logic, docs)
  - 7-2: 2 MEDIUM (Gate facade tests)
  - 7-3: 3 issues (rate limiting, route typing)
  - 7-4: 4 issues (missing tests, navigation)
  - 7-5: 2 MEDIUM (authorization tests)
  - 7-6: 2 MEDIUM (auto-dismiss, file list)
  - 7-7: 2 HIGH, 2 MEDIUM (middleware, dead code, ordering)

### 4. MVP-First Approach
- Deferred pagination to post-MVP (7-5)
- Deferred real-time WebSockets to V2
- Kept polling/manual refresh as simple MVP solution

### 5. French Localization Consistency
- All error messages, validation, and UI text in French
- Consistent "Discuter", "Envoyer", "Actualiser" terminology

---

## What Could Be Improved

### 1. Code Duplication in Composables
- `useConversationsList` and `useProducerConversationsList` are ~90% identical
- Same issue with `useConversation` / `useProducerConversation`
- **Action Item:** Consider generic factory pattern for future features

### 2. Frontend Test Coverage Gap
- Backend has 774 tests, frontend has minimal unit tests
- Stories 7-5, 7-6, 7-7 marked frontend tests as "N/A"
- **Action Item:** Establish frontend testing baseline for Epic 8+

### 3. Documentation Inconsistency
- project-context.md mentions "Pest syntax" but project uses PHPUnit
- Some stories didn't initially include sprint-status.yaml in File List
- **Action Item:** Update project-context.md to reflect actual PHPUnit usage

### 4. Gemini MCP Underutilized
- Dev Notes mandate Gemini MCP for UI tasks
- Not all stories documented Gemini usage
- **Action Item:** Clarify when Gemini is truly required vs optional

---

## Metrics & Statistics

| Metric | Value |
|--------|-------|
| Stories Completed | 7/7 (100%) |
| Backend Tests Added | +154 (620 → 774) |
| Test Assertions | 3127 total |
| Code Reviews Passed | 7/7 |
| Issues Found in Review | 17 total |
| Issues Fixed | 15 (2 LOW deferred) |
| New Files Created | ~35 |
| Files Modified | ~25 |

### Story Complexity Distribution
- Schema/Foundation: 1 story (7-1)
- Backend-Heavy: 2 stories (7-2, 7-5)
- Full-Stack: 4 stories (7-3, 7-4, 7-6, 7-7)

---

## Key Learnings & Patterns

### Pattern 1: Policy-Based Authorization
```
Conversation access = ConversationPolicy
  └── view(): User is Face OR Producer of candidature
  └── sendMessage(): view() + candidature.canAccessChat()
```
This pattern should be replicated for Rating/Reviews in Epic 8.

### Pattern 2: Participant-Based Resources
```
ConversationResource dynamically computes:
  └── other_participant based on current user type
  └── is_own_message for each message
```

### Pattern 3: Role Middleware
```
Face routes: middleware('face') → EnsureUserIsFace
Producer routes: middleware('producer') → EnsureUserIsProducer
```
This was missing for Producer until 7-7 review added it.

### Pattern 4: Composable State Management
```
useConversation returns:
  └── conversation, messages, isLoading, error
  └── loadConversation(), refreshConversation(), addMessage()
  └── isRefreshing (separate from isLoading for UX)
```

---

## Impact on Future Epics

### Epic 8 (Ratings & Reviews) - Direct Impact
- Can reuse Policy pattern from ConversationPolicy
- Rating access rule similar: "Only after mission completed"
- Will need RatingResource with computed fields like MessageResource

### Epic 9/10 (Dashboards) - Indirect Impact
- Unread message count already available via `unreadCountFor()`
- Can surface "X unread conversations" on dashboard
- Messages card already added to dashboards (7-7)

### Technical Debt to Address
1. Message pagination (deferred from 7-5)
2. Composable factory pattern to reduce duplication
3. Frontend test coverage

---

## Summary

### Epic 7 Outcome: SUCCESS ✅

The Messaging System epic delivered all 7 stories with:
- Full Face ↔ Producer bidirectional messaging
- Proper authorization (chat unlocks on candidature acceptance)
- Conversations list with unread badges
- Manual refresh functionality
- Comprehensive test coverage (131+ new tests)

### Top 3 Wins
1. Clean policy-based authorization pattern
2. Shared resources between Face/Producer
3. Rigorous code review catching 17 issues

### Top 3 Improvements for Epic 8
1. Reduce composable duplication with generic patterns
2. Add frontend unit tests
3. Update project-context.md documentation

---

## Change Log

| Date | Author | Change |
|------|--------|--------|
| 2026-01-28 | SM Agent | Retrospective completed |