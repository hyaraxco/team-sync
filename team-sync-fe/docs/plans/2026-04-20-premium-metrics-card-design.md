# Design: Premium Analytics Metric Card (Glass Badge Subtitle)

## Goal
Improve the visibility and aesthetic appeal of the `MetricCard` subtitle in its `highlight` state, resolving the issue where it appears "blank" (dark on dark) and lacks premium design quality.

## Background
The current `MetricCard` when highlighted uses a dark gradient background. The subtitle is rendered as plain text in `slate-300`, which the user reports as being too dark/blank. Additionally, redundant classes in the parent component are causing styling conflicts.

## Proposed Design

### 1. Subtitle Component (MetricCard.vue)
- **Concept:** "Glass Badge" style.
- **Implementation:**
    - Wrap the subtitle in an `inline-flex` container.
    - Style: `bg-white/10`, `backdrop-blur-md`, `border border-white/5`.
    - Typography: `text-[13px]`, `font-medium`, `text-white/70`.
    - Layout: `px-2.5`, `py-1`, `rounded-[8px]`, `mt-3`.

### 2. Parent Integration (PayrollAnalyticsEnhanced.vue)
- **Clean up:** Remove redundant `p-5` and `border-[#0B1042]` from the `MetricCard` instance.
- **Harmony:** Ensure the `main-card` class (dark gradient) and the `highlight` prop work together seamlessly.

### 3. Global CSS Refinement (main.css)
- **Optimization:** Refine the `.main-card` gradient to be smoother and ensure the inset box-shadow doesn't muddy the subtitle area.

## Trade-offs
- **Complexity:** Adds a few more tailwind classes and a nested element for the subtitle.
- **Performance:** Negligible impact from `backdrop-blur` on a single card element.

## Verification Plan
- Manual visual inspection of the "Total Payroll Cost" card.
- Verify contrast ratios for accessibility.
- Ensure no regressions on non-highlighted cards.
