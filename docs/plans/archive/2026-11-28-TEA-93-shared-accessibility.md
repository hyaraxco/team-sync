# TEA-93 / team-sync-84g: shared accessibility

Status: COMPLETED
Branch: `fix/team-sync-84g-shared-accessibility`
Agent: omp
Session: 01a22131-ed42-74bf-8695-0330a230fee5 (board G7nyi)

## Scope

Audit and fix verified gaps in shared shell and reused controls before page restyling: contrast on actual light/dark surfaces, accessible names and labels, visible focus, keyboard behavior, reduced motion, shared dialog focus entry/containment/return, and loading/empty/error announcements.

Preserve the existing HRIS visual direction: Plus Jakarta Sans, theme tokens, existing navigation, ENERGY 2 / RHYTHM 2 / MOTION 1, matching the canonical frontend design system. Accessibility fixes are not a screen redesign. Native behavior and existing components take precedence over new abstractions or dependencies.

## Boundaries

Do not duplicate closed focused work: muted tokens (y1k), current-route semantics (cwn), collapsed tooltips (mc3), drawer interaction/focus (e9x), initial drawer state (rvs), shared Select positioning (izu), or pagination/initial-empty fixes (rc2). Check their existing behavior as baseline. Do not change feature-page business workflows, permissions, API contracts, or data.

## Steps

1. Read prior ticket evidence and inspect shared components, their callers, tests, theme rules, and existing browser tooling.
2. Record evidence-backed findings and distinguish existing component defects from uncovered umbrella gaps. Trace nested dialogs and repeated form instances before choosing fixes.
3. Apply the smallest complete fixes with consumer-facing regression checks; preserve public component contracts unless an evidenced gap requires migrating callers.
4. Run focused tests after the final edit, then exercise actual rendered components in light/dark themes with keyboard and reduced motion. Measure actual contrast pairs rather than assuming token names imply compliance.
5. Maintain a concise checklist of tested surfaces, pairs, keyboard/focus behavior, theme, and limits. Update bead comments with Agent and Session lines. Move to test/review only when the corresponding gate is met; no close before approved, verified merge.

## Verified research findings

Source review, not a rendered audit:

| Area | Evidence and consumer impact | Minimum complete fix |
| --- | --- | --- |
| Dialog lifecycle | Both dialogs watch `show` without an immediate initial-open path. ModalWrapper has no opener restoration and includes disabled/hidden fields in its Tab selector. Both attach document key handlers. Task detail opens a confirmation above an already-open wrapper, so both Escape handlers can run. | One shared lifecycle for initial focus, enabled/visible Tab targets, topmost Escape, connected-opener return and cleanup. Exercise stacked dialogs in a real browser. |
| Dialog naming/layering | Title-less TaskDetailModal and ErrorModal have no dialog accessible name. Confirmation titles generate IDs from text. Wrapper teleports at z-index 9999; confirmation stays inline at z-index 50. | Explicitly connect custom visible headings, generate unique IDs, and use one consistent overlay layer. |
| Pagination names | Common pagination page-size select has no label. Team/payroll variants also have unnamed chevron buttons and omit current-page semantics. These controls reach staff directories, teams, projects, attendance, leave, overtime, payslips and payroll details. | Name page-size selects and previous/next controls, retain native keyboard behavior, expose current page. Preserve existing pagination algorithms and events. |
| Focus | Input removes its outline and substitutes low-opacity rings. Alert dismissal similarly removes the outline. The global current-color outline exists for most other controls. | Restore a high-contrast visible focus treatment on the actual light/dark surfaces; do not claim all focus indicators are absent. |
| Reduced motion | Existing reduced-motion rules cover view transitions/auth only. Notification entry/pulses, toast transitions/progress, modal transitions, table spinners and count-up JavaScript are not covered. | Apply motion-safe/reduce handling to touched components and stop count-up scheduling when reduction is requested, including preference changes. |
| Async announcements | Notification loading/error/empty branches have visible text but no status/alert semantics. ErrorBoundary fallback has retry but no alert. EmptyState lacks announcement semantics. Table loading has a named status but no visible loading text. Stats cards show `...` while loading. | Distinct perceivable status/error content, decorative icons hidden, retry/action preserved. Avoid announcing every animation frame. |
| Alert intent | Shared Alert supports `danger` but attendance correction passes `error`; it therefore receives success styling. | Align the caller to the existing intent contract or deliberately support both used intents; add a consumer-visible assertion. |
| Upcoming meetings | Failed upcoming fetch writes the store error, while the shared widget only tests loading/empty and therefore presents failure as no meetings. The store error is shared across multiple actions. | Separate request failure from true emptiness without reusing a stale unrelated store error; preserve retry behavior. |
| Header keyboard | Account uses ARIA menu roles without menu keyboard behavior. Header panels only close on click-outside; no Escape/focus-return handling. Skip link targets an outer container containing the header rather than the main landmark. | Prefer ordinary disclosure semantics with native links/buttons, Escape and focus return. Target the actual focusable main landmark. Do not alter drawer behavior. |

## Measured color pairs

Computed with the installed antislop contrast tool using source-declared colors. These are not yet computed-style/pixel measurements. Ordinary shared button, badge, toast and form text is normal text and requires 4.5:1.

| Foreground | Background | Ratio | Source use / result |
| --- | --- | --- | --- |
| `#FFFFFF` | `#6F96E3` | 2.94 | Blue-gradient midpoint: fails normal and large text. |
| `#FFFFFF` | `#3B82F6` | 3.68 | Dark brand-primary filled control: fails normal text. |
| `#FFFFFF` | `#EF4444` | 3.76 | Red-500 button gradient endpoint / unread badge: fails normal text. |
| `#FFFFFF` | `#F59E0B` | 2.15 | Amber-500 confirmation gradient endpoint: fails normal and large text. |
| `#CA8A04` | `#FFFFFF` | 2.94 | Yellow-600 toast message before additional opacity: fails normal text. |
| `#16A34A` | `#FFFFFF` | 3.30 | Green-600 toast message before additional opacity: fails normal text. |
| `#991B1B` | `#1E293B` | 1.76 | Red-800 toast title in dark mode: fails normal and large text. |
| `#DC2626` | `#1E293B` | 3.03 | Shared form/notification error text in dark mode: fails normal text. |
| `#3B82F6` | `#1E293B` | 3.98 | Small dark-theme brand link text: fails normal text. |
| `#9CA3AF` | `#FFFFFF` | 2.54 | Gray-400 control icon: fails 3:1 non-text contrast. |
| `#6B7280` | `#E5E7EB` | 3.90 | Secondary light text on muted surface: fails normal text if used there. |
| `#94A3B8` | `#334155` | 4.04 | Secondary dark text on muted surface: fails normal text if used there. |
| `#0C51D9` | `#FFFFFF` | 6.57 | Light cobalt on white: passes normal text. |

Fix semantic foregrounds/backgrounds locally or through existing shared tokens; do not repaint all feature pages or assume the already-fixed muted token is the cause. Preserve status meaning through text, not color alone.

## Preventive edges, not demonstrated screen failures

- Input/Select/TextArea derive fallback IDs from names/labels, so equal instances collide. Representative live callers supply distinct IDs; no actual duplicate screen instance was established. Use Vue `useId()` for fallback IDs while preserving explicit IDs.
- Undeclared control attributes fall through to wrapper divs. No present caller passing a broken disabled/readonly attribute was established. Any forwarding change must preserve wrapper layout classes and compose help/error descriptions.
- Shared action buttons omit `type="button"`; inspected callers do not nest them inside forms. Treat as a reusable-control edge, not a proven unintended submission.

## Dialog implementation decision

Native `dialog.showModal()` can remove the custom focus trap and provide stacking/inert behavior. It is not a drop-in migration: current toasts teleport to body and would sit outside the native top layer, so errors emitted while a modal remains open could become imperceptible. Resolve toast placement/announcement and regression coverage before selecting native dialog. Otherwise consolidate the existing modal lifecycle with explicit topmost ownership; do not add a second independent trap.

## Separate existing business defect

TaskDetailModal's comment-delete handler schedules a confirmation callback and also calls the delete API immediately after opening the confirmation. Cancel cannot prevent that immediate deletion. This is not caused by TEA-93 and should be tracked as a focused defect, not silently bundled into visual cleanup. Never exercise it against real comments during this audit.

## Verification and evidence

- Read closed ticket evidence and current sources/callers/tests. No LSP is configured; source search was used as fallback.
- Contrast calculations above ran successfully. No app tests, build, reset-capable E2E, browser keyboard walkthrough or assistive-technology checks have run during this research phase.
- Installed Playwright package points to a Chromium executable that is absent. Arrange an available isolated browser for rendered verification; absence is not permission to claim success from jsdom.
- Existing tests mostly stub dialog wrappers and check visible state text, so they do not prove real focus containment, modal stacking or live announcements.
- Keep the bead `in_progress`; research is not implementation-ready-for-test.

## Standards used

- [WAI-ARIA modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/): focus entry, contained tab sequence, Escape, focus return and accessible naming.
- [WAI-ARIA disclosure navigation example](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/examples/disclosure-navigation/): ordinary navigation need not use menu roles; Escape closes and restores focus.
- [Vue useId documentation](https://vuejs.org/api/composition-api-helpers.html#useid): per-instance accessibility IDs. Context7 served current documentation because a 3.5-specific snapshot was unavailable.

## Additional dialog requirements

User override during implementation: footer Cancel/Confirm remain disabled while loading, but header Close, backdrop and Escape still dismiss confirmation. Dismissal does not abort the pending API request. Wrapper loading remains caller-controlled. Warning messages are connected with aria-describedby; processing has status text. Shared lifecycle preserves previous overflow until the last modal closes.

## Research delivery gate

- Evidence PASS: source findings and calculated pairs are distinguished from unperformed rendered checks.
- Scope PASS: closed focused tickets are baseline; preventive API edges and the destructive comment defect are separately identified.
- Design direction PASS: existing HRIS typography, palette and ENERGY 2 / RHYTHM 2 / MOTION 1 remain unchanged.
- Implementation gate NOT RUN: no UI code was changed or delivered. Keyboard, computed-style contrast, screen-reader behavior and reduced-motion behavior still require rendered verification.


## Implementation acceptance checklist

- [x] Shared controls, semantic error feedback and an error toast passed rendered axe contrast/name checks in settled light/dark themes; dialog/toast contrast also passed while open. This is a bounded harness, not every feature-page pairing or toast intent.
- [x] Pagination/form names and attribute forwarding covered by focused tests; browser label and name checks passed.
- [x] Dialog tests cover initial focus, Tab containment, unique names, connected-opener return and reverse DOM ordering. Browser checks passed nested Escape/focus return, disabled/hidden exclusion, toast focusability and reverse-order layering.
- [x] Reduced-motion preference suppresses nonessential shared CSS and JavaScript animation: count-up and preference changes, dialogs/toasts, statistics, footer actions, alerts, retry, pagination/search, forms, upcoming meetings and notification feedback. Native focus remains visible.
- [x] Loading/empty/error and upcoming retry isolation covered by focused regressions; no live region wraps animation frames.
- [x] Focused component/caller tests and isolated rendered checks recorded below.
- [x] Limits explicit: no whole-app WCAG claim, screen-reader speech verification, production data mutation or reset-capable E2E.

## Implementation verification

- Focused Vitest component/caller runs passed after fixes. Node 26 native Web Storage interferes with jsdom localStorage; use NODE_OPTIONS=--no-experimental-webstorage for local verification. No production workaround added.
- Headless installed Helium exercised actual Vue components via temporary Vite harness, with no API/data access. Light/dark axe scans included a visible error toast; open dialog plus toast scan also passed selected contrast/name rules.
- Input keyboard outline: 3px solid, light rgb(3,7,18), dark rgb(241,245,249). Narrow 390px viewport had no horizontal overflow. Screenshot saved outside repository in Ghostex image storage.
- Reverse component DOM-order check passed both numeric z-index ordering and topmost-only Escape in browser. Unit tests check effective ancestor inertness because Vue Test Utils inserts transition stubs.
- Separate destructive comment-deletion defect tracked as team-sync-bri; not fixed or exercised here.
- Closing pending confirmation is not request cancellation. No release, merge, full-app accessibility certification or screen-reader testing is implied.

## PR review remediation

- Remove the obsolete badge color-class assertion; retain unread-count behavior coverage.
- Guard confirmation-opening handlers while their existing request remains pending. Close, backdrop and Escape still dismiss; dismissal does not cancel requests or permit another action to replace the pending one.
- Match immediate reduced-motion/zero-duration rounding to animated output, including fractional regression coverage.
- Align attendance Alert callers with required titles and the shared close event; remove the unsupported dismissal attribute. Verify dismissal clears parent errors and a later failure is visible.
- Run affected component/caller tests locally after the final edit, then require FE Tests on the new PR head. No merge or release is authorized.
- Review remediation verification: 19 affected Vitest files passed (229 tests), including pending-dismiss/reopen, Alert caller dismissal/reappearance and fractional precision cases. Actual Alert and AnimatedValue passed an isolated headless Chromium smoke via Playwright `chromium.launch()` with `executablePath: '/Applications/Helium.app/Contents/MacOS/Helium'`, at 390px with reduced motion. Two earlier `browser.open()` helper attempts failed; the successful owned Playwright process did not touch user tabs. The dark Alert screenshot is outside the repository. No application data or APIs accessed.
