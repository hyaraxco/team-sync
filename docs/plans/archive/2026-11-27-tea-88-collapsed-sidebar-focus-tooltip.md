# Collapsed sidebar tooltips on keyboard focus

Status: COMPLETED — merged as `fed5d55` via PR #149 (`team-sync-mc3`)
Created: 2026-11-27

## Problem

The collapsed admin sidebar renders 44px icon-only links. Labels were hidden with
`v-show="!isCollapsed"`, which compiles to `display: none`. That removed the text
from the accessibility tree, so the links lost their accessible names, and the
tooltip was a CSS `::after` reading `attr(data-tooltip)` revealed only on `:hover`.
Keyboard users tabbing the rail saw nothing.

A second defect in the same rail: each link carried `focus:bg-white`. Focused
links got a white background while the label kept `text-brand-white` on the active
route, making the current link unreadable in dark mode.

## Change

1. Label span is the tooltip. `.sidebar-collapsed .nav-link > span` is positioned
   outside the 44px box (`left: calc(100% + 12px)`), so it never covers the focused
   control, and is revealed on `:hover` **and** `:focus-visible`. One text node now
   serves both the accessible name and the visible hint, so they cannot drift.
2. `v-show="!isCollapsed"` removed from the label spans. The text stays in the DOM
   in both states; the collapsed CSS hides it visually without `display: none`.
3. `data-tooltip` removed from all 23 links — the span supplies the text now, so the
   attribute was dead weight and a second copy of every label.
4. `focus:bg-white` replaced with `focus-visible:ring-2 focus-visible:ring-brand-primary`,
   which matches the existing `hover:ring-2` affordance and works in both themes.
   The global `*:focus-visible` outline still applies.

Colors are the existing tokens: `--color-brand-dark` on `--color-surface`
(16.84:1 light, 13.35:1 dark), both above WCAG AA for normal text.

## Verification

- `bunx vitest run src/tests/admin/components/Sidebar.smoke.test.js` — 17 passed,
  including a new case asserting every collapsed link keeps a non-empty label span
  without `display: none`.
- `bunx vite build` — succeeded; the emitted stylesheet contains the `> span` tooltip
  rule and the `:focus-visible` reveal selectors.
- `src/tests/layouts/Admin.layout.test.js` fails 6 cases identically on an unmodified
  tree; pre-existing, unrelated to this change.
- Not verified: rendered pixel output. No Playwright browser was available locally;
  `bun run test:a11y` needs a running preview server plus Chrome.

## Residual

`team-sync-n34` (shared RBAC shell) still owns consolidating this rail; the tooltip
pattern here is the reference behavior for that work.
