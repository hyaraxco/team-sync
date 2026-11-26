# TEA-87 — Close mobile sidebar accessibly and expose its state

Bead: `team-sync-e9x` · Status: COMPLETED · Depends on `team-sync-rvs` (closed, PR #137)
Delivered in PR #147 (`fix/team-sync-e9x-mobile-sidebar-a11y`).

## Skills applied

- `ponytail` — smallest change that satisfies the three acceptance criteria; no new media-query abstraction, no focus trap, no animation work.
- `antislop` — accessibility contract only; no visual redesign, no new tokens or components.
- `antislop-human` — trigger state, Escape, focus return, off-canvas semantics.

## Scope

`team-sync-fe` mobile drawer contract, three surfaces:

1. Header trigger: `aria-expanded` mirrors drawer state, `aria-controls` points at the drawer.
2. Drawer: dialog/off-canvas semantics only while open; existing `id="sidebar"` is the `aria-controls` target.
3. Layout: Escape closes the drawer and returns focus to the trigger; backdrop click keeps closing it.

Out of scope (deliberate): focus trap inside the drawer, `inert` on the off-canvas subtree, restyling. The first two need viewport awareness, which is `team-sync-n34` shell work.

## Approach

- `Header.vue` takes `isOpen` and `sidebarId` props, exposes `focusMenuTrigger()`.
- `Admin.vue` owns the Escape listener and the focus return on close, passes `isOpen` down.
- `Sidebar.vue` adds `role="dialog"` / `aria-modal` conditionally, plus an accessible name.
- Focus return lives in one `watch(isOpen)`, so Escape, the close button, the backdrop and navigation all restore focus identically.
- Desktop behaviour untouched: the drawer stays `lg:relative` with `lg:translate-x-0`. A `(min-width: 1024px)` `matchMedia` guard closes a drawer left open by a resize, so the persistent desktop sidebar never inherits modal semantics.

## Verification

- Focused Vitest: `Header.smoke.test.js`, `Sidebar.smoke.test.js`, `layouts/Admin.layout.test.js`, `composables/useSidebar.test.js` — 4 files, 54 tests passed.
- ESLint on the six changed files with `--max-warnings 0`: clean.
- `bun run build`: succeeded; only pre-existing chunk-size warnings.
- Not run: full Vitest suite, Playwright E2E, `test:a11y` axe sweep. `fe-tests` was pending on the PR at handoff.
- `prettier --check` warns on the changed files, but untouched files (`NotificationPanel.vue`, `ErrorBoundary.vue`) warn identically, so formatting was left alone.
