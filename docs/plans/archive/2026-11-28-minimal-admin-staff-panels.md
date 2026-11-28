# Minimal admin and staff panels

Status: COMPLETED
Branch: feat/minimal-admin-staff-panels

## User scope

Align every admin and staff page with the canonical frontend design system. Remove decorative multicolor treatments; make main content use the existing neutral card conventions consistently. Authentication is excluded. This is separate page restyling after TEA-93, not additional accessibility-ticket scope.

## Shared implementation contract

- Existing MainCard default-slot mode is the plain neutral panel; reuse it for unframed main lists/forms/detail content. Existing DataTableCard and equivalent bordered surface panels already satisfy the requirement; do not nest a new outer card around them or wrap an entire dashboard in one card.
- White/theme surface, fine brand border, rounded-2xl, responsive p-4 sm:p-5. App canvas stays neutral. Use existing CSS theme variables and Tailwind utilities, not new global overrides or new components.
- Navy text, subdued secondary text, cobalt only for primary action/selection/focus. Remove decorative rainbow metric icons, gradients and tinted section panels. Status, error, destructive controls and data-series distinctions retain meaningful accessible colors; never neutralize actual error/status meaning.
- Retain existing page hierarchy, permissions, routes, stores, API behavior, loading/empty/error feedback and keyboard/focus contracts. No backend changes. Existing feature bugs are not this task.
- Preserve Plus Jakarta Sans, existing spacing/radii, and ENERGY 2 / RHYTHM 2 / MOTION 1. No assets, dependencies, invented content or speculative abstractions.
- Inspect every page and its rendered child components. No change required for an already-compliant surface; record those in the inventory.

## Work slices

1. Shared visual components and design-system convention.
2. Admin attendance, staff directory, teams, meeting.
3. Admin performance and projects.
4. Admin payroll, analytics, dashboard, settings, notifications, upgrade.
5. All staff pages and staff components.

Each slice supplies its page inventory, changed surfaces and affected test paths. No checks during editing. Parent integrates and runs affected verification after the final code edits, then renders actual component/page surfaces at mobile and desktop widths. Remote FE CI must pass on the published head. No merge/release without approval.
