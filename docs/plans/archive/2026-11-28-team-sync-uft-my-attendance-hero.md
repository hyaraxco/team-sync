# style(fe): restore hero background image banner in my-attendances

Ticket: team-sync-uft
Status: COMPLETED
Branch: style/team-sync-uft-my-attendance-hero

## User Scope & Acceptance Criteria
- Restore the previous hero banner styling with the background image (`https://images.unsplash.com/photo-1497366216548-37526070297c`), dark overlay, white text, and glassmorphic badge/button placement on `/admin/attendance/my-attendances` header card.
- Keep all interactive buttons (Clock In, Clock Out, Request Leave, Work Mode selector, Auto-present Remote indicator) functionally intact and accessible.
- Maintain responsive layout across desktop and mobile viewports (598px - 959px).
- Verify with unit and smoke tests in Vitest and verify production build.
- Open PR for review with passing CI before merge.
