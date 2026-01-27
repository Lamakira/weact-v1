# Epic 6 Retrospective: Candidature Workflow

**Date:** 2026-01-28
**Epic:** Candidature Workflow (9 stories)
**Status:** Complete

---

## Summary

Epic 6 implemented the complete candidature workflow allowing Faces to apply to missions and Producers to manage applications. The epic included database schema, application flow, viewing candidatures, accept/reject actions, 2-step confirmation, and in-app notifications.

---

## Stories Completed

| Story | Description | Tests | Issues |
|-------|-------------|-------|--------|
| 6-1 | Candidature Database Schema | 30 | 4 MEDIUM |
| 6-2 | Face Apply to Mission | 16 | 5 (4M, 1L) |
| 6-3 | Face View My Candidatures | 14 | 4 (1H, 3M) |
| 6-4 | Producer View Mission Candidatures | 14 | 4 (1H, 3M) |
| 6-5 | Producer View Candidate Full Profile | 13 | 0 |
| 6-6 | Producer Accept Candidature | 13 | 0 |
| 6-7 | Producer Reject Candidature | 13 | 0 |
| 6-8 | Face Confirm Mission | 13 | 0 |
| 6-9 | Face Candidature Status Notifications | 22 | 4 (1H, 3M) |

**Total Tests Added:** 148 tests
**Total Backend Tests:** 620 (2705 assertions)

---

## What Went Well

- All 9 stories completed successfully
- Consistent architectural patterns across all stories
- Later stories (6-5 to 6-8) had zero code review issues - demonstrating learning curve improvement
- Clean separation between Face and Producer workflows
- Reusable components (StatusFilter, CandidatureCard patterns)
- 2-step validation system (accept → confirm) properly implemented
- Notification system foundation laid for future features

---

## Challenges

- **UI/UX Fixes:** Some frontend components required post-implementation UI/UX adjustments
- Early stories had more code review findings that were progressively eliminated

---

## Key Technical Decisions

1. **Composite Indexes:** Added (mission_id, status) and (face_id, status) for query performance
2. **Helper Methods:** Added `allowsChatAccess()` and `allowsRatings()` to CandidatureStatus enum for future epics
3. **Polling Notifications:** MVP approach with 30-second polling (WebSockets planned for V2)
4. **Race Condition Prevention:** Request ID tracking in frontend composables

---

## Code Review Patterns

| Issue Type | Count | Examples |
|------------|-------|----------|
| Null Safety | 3 | Producer null check, date parsing |
| Authorization | 2 | Redundant middleware checks |
| UX Improvements | 4 | Silent failures, navigation, polling |
| Validation | 2 | Form Request, NaN validation |

---

## Impact on Future Epics

### Epic 7 (Messaging)
- Chat access is unlocked when `candidature.status = 'accepted'`
- `allowsChatAccess()` helper method ready

### Epic 8 (Ratings)
- Ratings enabled when `candidature.status = 'completed'`
- `allowsRatings()` helper method ready

---

## Metrics

- **Stories:** 9/9 completed (100%)
- **Scope Changes:** None
- **Surprises:** None
- **Agent Model:** Claude Opus 4.5 (claude-opus-4-5-20251101)

---

## Conclusion

Epic 6 was completed successfully with no scope changes. The candidature workflow is fully functional with proper authorization, status transitions, and in-app notifications. The codebase is well-tested and ready for Epic 7 (Messaging) which depends on the chat access rules established here.
