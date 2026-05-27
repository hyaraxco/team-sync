# Hybrid Schedules Simplification Plan

**Status:** COMPLETED
**Created:** 2026-05-24
**Branch:** `feat/dark-mode-css-vars`

## Party Mode Synthesis: Hybrid schedules UI vs backend meaning

### Participants
- Budi (PM) — Hybrid schedules are still relevant, but the UI should match the actual user mental model: work pattern first, not location details first.
- Gani (HR) — Hybrid and remote must remain distinct in attendance/payroll rules; however, daily location should not dominate the HR screen if the business only needs auto-present and exception handling.
- Dede (Backend) — Backend still resolves hybrid by planned work mode per date and creates mismatches when actual mode differs, so the app currently depends on a work-mode model even if the UI can be simplified.
- Eka (Frontend) — The current tab can be simplified visually to look like an attendance rule table, but the override tab should stay only if HR really manages exceptions.

### Agreements
- Hybrid schedules are not fake or redundant; they are part of attendance logic.
- The current UI is too location-heavy for the business meaning the user described.
- Remote employees should stay simple: auto-present / 8-hour work completion, without forcing location management in the main list.

### Disagreements
- Budi and Eka prefer hiding location semantics from the main hybrid table.
- Gani and Dede keep the override concept available because it feeds attendance mismatch and payroll controls.

### Decision
**What to do:** Simplify the Hybrid Schedules page UI so it shows work pattern and exception handling clearly, but keep the backend override model intact for now.

### Rationale
**Why this wins:** The docs and backend show hybrid schedules are used by `HybridScheduleResolver` and `AttendanceClassifier` to determine planned work mode and policy mismatch. That means removing the concept entirely would be risky. But the user’s business description shows the HR screen should not overemphasize location. So the correct move is to reduce UI noise, not delete the underlying rule model.

### Trade-offs
- **Gaining:** Clearer HR UI, less confusion for hybrid/remote rules, better match to user mental model.
- **Giving up:** Full abstraction of location semantics in the UI; backend still keeps planned mode and override logic.

### Risks
| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| HR assumes hybrid overrides are removed | Medium | Keep the override tab but rename/helptext it as exception handling, not location management. |
| Backend still feels more complex than UI | Medium | Later phase can introduce a simpler presentation layer without changing resolver logic. |
| Remote policy gets mixed with hybrid policy | Low | Keep remote as separate rule text in docs and UI labels. |

### UI Simplification Spec (Before → After)

**Tab 1: Schedules**

| Before (location-heavy) | After (pattern-focused) |
|---|---|
| Column: "Base Schedule (Mon - Fri)" | Column: "Work Pattern" |
| Badge label: "Office" | Badge label: "Office" |
| Badge label: "Remote" | Badge label: "WFH" |
| Badge icon: Building (office) | Badge icon: Building (office) — unchanged |
| Badge icon: Home (remote) | Badge icon: Home (remote) — unchanged |
| `locationMap` object | `workPatternMap` object (same structure, renamed) |

**Tab 2: Override Requests → Exceptions**

| Before | After |
|---|---|
| Tab label: "Override Requests" | Tab label: "Exceptions" |
| Column: "Current Location" | Column: "Current" |
| Column: "Requested Location" | Column: "Requested" |
| Empty title: "No pending overrides" | Empty title: "No pending exceptions" |
| Empty subtitle: "Pending override requests will appear here." | Empty subtitle: "When employees request schedule changes, they appear here for approval." |
| Approve modal title: "Approve Override Request" | Approve modal title: "Approve Schedule Exception" |
| Reject modal title: "Reject Override Request" | Reject modal title: "Reject Schedule Exception" |
| Approve confirmation text: "Confirm approval for this hybrid schedule override request." | Approve confirmation text: "Confirm approval for this schedule exception." |

### Action Items
1. [ ] Update `HybridScheduleList.vue` — rename `locationMap` → `workPatternMap`, update labels per spec table above.
2. [ ] Update tab 2 labels from "Override Requests" → "Exceptions", update column headers.
3. [ ] Update modal titles and confirmation text per spec.
4. [ ] Update empty state text per spec.
5. [ ] Keep backend resolver and override logic unchanged.
6. [ ] Run `bun run test` — no regressions.
7. [ ] Verify no console errors in browser.

### Acceptance Criteria
- [ ] Schedules tab column says "Work Pattern", badges show "Office"/"WFH"
- [ ] Second tab label says "Exceptions"
- [ ] Override table columns show "Current"/"Requested" (not "Current Location"/"Requested Location")
- [ ] Modal titles say "Schedule Exception" not "Override Request"
- [ ] All 981+ FE tests pass
- [ ] No console errors

---
*Note: Party mode subagents were unavailable due external API key error, so this synthesis was done directly from repo docs and code.*
*Note: Backend zero changes — only UI label/text updates. Confidence: 97%.*