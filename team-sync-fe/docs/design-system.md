# Team Sync Frontend Design System

Status: Canonical direction for future UI work. Existing screens are not yet fully aligned.
Last reconciled with original HRIS source and current shared components: 2026-11-25

## Source of truth

This system follows the original HRIS frontend source preserved in the development backup, especially its `src/assets/css/input.css`, `components/admin/Sidebar.vue`, `components/admin/Header.vue`, and dashboard components. Those HRIS files establish the inherited visual language. The unrelated Point of Sale `overview.html` is not the product source of truth.

The current Team Sync code shows what ships; this document sets the target for new and revised UI. Existing screens are not presumed compliant. Role-specific pages may have different content and hierarchy when their work requires it, while shared tokens and components remain consistent.

Reading this as: an Indonesian HRIS for employees and HR, Manager, Finance and Superadmin operators. Calm, structured enterprise workspace. ENERGY 2 / RHYTHM 2 / MOTION 1. Light mode by default, with a user-controlled dark theme.
RBAC role changes what an employee can access; `job_title` does not grant permissions. Keep role-specific screen content tied to permissions, not employment position labels.

### Visual decisions

- Navy (`#0C1C3C`) and cobalt (`#0C51D9`) continue the HRIS source identity and distinguish primary actions from neutral surfaces.
- Plus Jakarta Sans stays because the original HRIS source uses it and current UI loads it globally.
- White surfaces, a cool light-gray canvas, fine borders and rounded cards support employee and payroll data without making every surface float.
- Keep the deep navy gradient as a focal treatment for active navigation and at most one role-relevant hero metric on a dashboard. Do not extend it to ordinary cards or every CTA.
- Use semantic status colors only for real workflow states. Pastel icon tiles are optional, meaning-bearing accents, not decoration required on every card.
- Keep the shared shell recognizable while arranging HR, Manager, Finance and employee dashboards around their distinct decisions.
- Motion stays to useful transitions and feedback. No perpetual pulse for static information.

### Adoption

When revising a screen, identify its primary user decision and role/permission scope. Reuse shared tokens/components, preserve correct workflows and data, and change only hierarchy or states that do not serve that task. Do not apply one dashboard or card layout to every role.

The source design establishes the visual language, not a pixel-for-pixel rollback. Keep a deliberate difference when the feature needs it; document its reason and test it.


---

## Table of Contents

1. [Design Tokens](#1-design-tokens)
2. [Typography](#2-typography)
3. [Color System](#3-color-system)
4. [Spacing & Border Radius](#4-spacing--border-radius)
5. [Elevation & Shadows](#5-elevation--shadows)
6. [Iconography](#6-iconography)
7. [Layout & Page Shell](#7-layout--page-shell)
8. [Components](#8-components)
   - [Alert](#81-alert)
   - [AnimatedValue](#82-animatedvalue)
   - [StatsCard](#83-statscard)
   - [MainCard](#84-maincard)
   - [StatusBadge](#85-statusbadge)
   - [EmptyState](#86-emptystate)
   - [SearchFilter](#87-searchfilter)
   - [ModalWrapper](#88-modalwrapper)
   - [ConfirmationModal](#89-confirmationmodal)
   - [ToastContainer](#810-toastcontainer)
   - [Form — Input](#811-form--input)
   - [Form — Select](#812-form--select)
   - [Form — TextArea](#813-form--textarea)
   - [SidebarTooltip](#814-sidebartooltip)
9. [Navigation](#9-navigation)
   - [Sidebar](#91-sidebar)
   - [Header](#92-header)
10. [Composables](#10-composables)
11. [Badge & Status Utilities](#11-badge--status-utilities)
12. [Dark Mode](#12-dark-mode)
13. [Accessibility](#13-accessibility)
14. [Frontend Architecture Rules](#14-frontend-architecture-rules)

---


## 1. Design Tokens

Runtime source of truth: `tailwind.config.js` and `src/assets/css/input.css`. The palette below records the inherited HRIS direction; inspect actual rendered classes before assuming every view uses each token.

### Primary Palette

| Token | Hex | Tailwind Class | Usage |
|-------|-----|----------------|-------|
| `primary-50` | `#eff6ff` | `bg-primary-50` | Hover tints |
| `primary-100` | `#dbeafe` | `bg-primary-100` | Icon backgrounds |
| `primary-500` | `#3b82f6` | `bg-primary-500` | Accent elements |
| `primary-600` | `#2563eb` | `bg-primary-600` | Default button bg |
| `primary-700` | `#1d4ed8` | `bg-primary-700` | Button hover |
| `primary-900` | `#1e3a8a` | `bg-primary-900` | Deep accents |

### Brand Colors

| Token | Hex | Tailwind Class | Usage |
|-------|-----|----------------|-------|
| Brand Dark | `#0C1C3C` | `text-brand-dark` / `bg-brand-dark` | Primary text |
| Brand Light | `#6B7280` | `text-brand-light` / `bg-brand-light` | Secondary text; verify small text contrast on its actual surface |
| Brand Border | `#DCDEDD` | `border-brand-border` | Brand structural border |
| Brand Primary | `#0C51D9` | `text-brand-primary` / `bg-brand-primary` | Primary actions and focus |
| Brand White | `#FFFFFF` | `.text-brand-white` | Text on dark surfaces |
| Success | `#059669` | `.text-success` / `bg-success-*` | Positive status only |
| Danger | `#DC2626` | `.text-danger` / `bg-danger-*` | Error/destructive status |
| Warning | amber scale | `bg-warning-*` | Warning status |

### Gradient Tokens

| Name | Value | Class | Usage |
|------|-------|-------|-------|
| Brand blue | `linear-gradient(265.5deg, #0C51D9 5.45%, #6F96E3 52%, #0C51D9 100.36%)` | `.blue-gradient` | Primary action or specific brand treatment |
| Navy focal | `linear-gradient(266deg, #040724 5.45%, #0C1448 52%, #040724 100.36%)` | `.main-card`, `.nav-link-active` | Focal metric or active route only |
| Auth overlay | `from-brand-primary/90 via-[#1a3a6e]/85 to-[#0a1f44]/95` | `Auth.vue` | Contrast behind auth copy; hero image is separate |

---


## 2. Typography

**Font family:** `Plus Jakarta Sans` (loaded via Google Fonts / CDN), applied globally on `html`.

```css
html {
    font-family: "Plus Jakarta Sans", sans-serif;
    color: #0C1C3C;
}
```

### Type Scale

| Role | Size | Weight | Class / Usage |
|------|------|--------|---------------|
| Page Title | `text-2xl` / `text-xl` | `font-extrabold` | Header `<h2>` |
| Section Title | `12px` | `600`, uppercase, `0.05em` tracking | `.section-title` |
| Card Value (large) | `text-3xl` / `text-5xl` | `font-extrabold` | `MainCard` metric |
| Card Value (small) | `text-2xl` / `text-3xl` | `font-extrabold` | `StatsCard` metric |
| Card Label | `text-sm` | `font-medium` | Card title text |
| Body | `text-sm` / `text-base` | `font-normal` | General prose |
| Subtitle | `text-sm` | `font-normal` | Header page subtitle |
| Nav Item | `text-base` | `font-medium` | Sidebar links |
| Label (form) | `text-sm` | `font-medium` | Input / Select labels |
| Badge | `text-xs` | `font-semibold` | `StatusBadge` |
| Tooltip | `12px` | `500` | `SidebarTooltip` |
| Error message | `text-xs` | `400` | Form field errors |

Metric, money and count values use `tabular-nums` so digit-width changes do not shift comparisons. Do not show invented deltas or praise; a trend needs real comparison data.

---

## 3. Color System

### Semantic Colors

| Intent | Current token/pattern |
|--------|-----------------------|
| App canvas | `--color-surface-raised` (`#F9FAFB` light, `#0F172A` dark) |
| Card/header surface | `--color-surface` (`#FFFFFF` light, `#1E293B` dark) |
| Raised/hover surface | `--color-surface-overlay` |
| Structural border | `border-brand-border` or `--color-border-default` |
| Primary text | `text-brand-dark` / `--color-text-primary` |
| Secondary text | `text-brand-light` / `--color-text-secondary`; verify AA on the rendered surface |
| Active navigation | `.nav-link-active`, inherited navy gradient |

> **Note:** Dark mode uses CSS custom properties in `src/assets/css/input.css`, toggled by `useDarkMode()`. Use theme tokens instead of Tailwind `dark:` classes.

### Surface tokens

| Token | Light | Dark | Purpose |
|-------|-------|------|---------|
| `--color-surface-raised` | `#F9FAFB` | `#0F172A` | App canvas |
| `--color-surface` | `#FFFFFF` | `#1E293B` | Standard card/header surface |
| `--color-surface-overlay` | `#F3F4F6` | `#273549` | Raised/hover surface |
| `--color-brand-border` | `#DCDEDD` | `#334155` | Brand boundary |

`bg-gray-*` and literal colors remain in legacy screens. Convert shared components and touched views to theme tokens incrementally; do not assume a class is theme-safe without checking its rendered color.

### Status / Semantic Palette

| Status | Background | Text | Used in |
|--------|-----------|------|---------|
| Success / Active | `green-100` | `green-700` | Badges, alerts |
| Info / Active (project) | `#EBF8FF` | `#1E40AF` | Project badges |
| Warning / Pending | `yellow-100` | `yellow-700` | Leave, payroll badges |
| Danger / Rejected | `red-100` | `red-700` | Alert, badges |
| Purple / Planning | `purple-100` | `purple-700` | Team / project status |
| Gray / Inactive | `gray-100` | `gray-700` | Default fallback |

---


## 4. Spacing & Border Radius

### Spacing

The project uses **Tailwind's default spacing scale**. Recurring patterns:

| Context | Value | Class |
|---------|-------|-------|
| Page padding (lg) | `32px` | `p-8` |
| Page padding (md) | `24px` | `p-6` |
| Page padding (sm) | `16px` | `p-4` |
| Page padding (xs) | `12px` | `p-3` |
| Card padding | `16–20px` | `p-4 sm:p-5` |
| Modal padding | `24px` | `p-6` |
| Nav link padding | `14px 20px` | inline `.nav-link` |
| Section vertical gap | `24px` | `space-y-6` |
| Form field gap | `8px` | `space-y-2` |
| Button gap | `8px` | `gap-2` |
| Icon–label gap | `10px` | `gap-[10px]` |

### Border radius
Use a small Tailwind radius scale and distinguish controls, cards, badges and avatars. These are target defaults for new or revised shared components; existing screens are not yet uniform.

| Component | Target | Tailwind |
|-----------|--------|---------|
| Standard card | `16–20px` | `rounded-2xl` |
| Input/select | `8–12px` | `rounded-lg` or `rounded-xl` |
| Button | `8–12px` | `rounded-lg` or `rounded-xl` |
| Small icon tile | `8–12px` | `rounded-lg` or `rounded-xl` |
| Avatar | Circle | `rounded-full` |
| Status badge | `4–8px` | `rounded-md` |

Use arbitrary radii only for a documented screen need. Do not make every element pill-shaped.


---

## 5. Elevation & Shadows

| Layer | Current pattern | Usage |
|-------|-----------------|-------|
| Standard surface | `border border-brand-border` | Flat cards and panels on the page canvas |
| Hover/focus | `hover:ring-2 hover:ring-primary-500/20` or theme-token equivalent | Visible interaction, not persistent elevation |
| Navy focal card | `.main-card` inset navy treatment | One role-relevant primary metric when useful |
| Modal backdrop | `bg-black/50` | Dialog layer |
| Popover/toast | `shadow-md` / `shadow-lg` | Overlay elevation only |

Keep shadows rare. `StatsCard` currently uses a tinted icon tile and border, not a colored left stripe. Do not add decorative left stripes or page-wide shadows.

---

## 6. Iconography

**Library:** [Lucide Vue Next](https://lucide.dev/) — used exclusively throughout the app.

```js
import { HomeIcon, UsersIcon, CalendarIcon } from 'lucide-vue-next'
```

### Sizing Convention

| Context | Size | Class |
|---------|------|-------|
| Sidebar nav icons | `20×20px` | `w-5 h-5` |
| Header action icons | `20×20px` | `w-5 h-5` |
| StatsCard icon | `20–24px` | `w-5 h-5 sm:w-6 sm:h-6` |
| MainCard icon | `24–32px` | `w-6 h-6 sm:w-8 sm:h-8` |
| Modal header icon | `24×24px` | `w-6 h-6` |
| Form field prefix icon | `20×20px` | `h-5 w-5` |
| Toast icon | `20×20px` | `w-5 h-5` |
| Badge / inline icon | `16×16px` | `w-4 h-4` |
| EmptyState icon | `40–64px` | `w-10–w-16` |

### Dynamic Icon Resolution

`StatsCard` and `MainCard` resolve icons by string name at runtime:

```js
import * as Icons from 'lucide-vue-next'
const resolveIcon = computed(() => Icons[props.iconName] || Icons.HelpCircle)
```

Pass `iconName="UsersIcon"` as a prop — if the name is invalid it falls back to `HelpCircle`.

---


## 7. Layout & Page Shell

### Admin Layout (`layouts/Admin.vue`)

Full-screen two-column shell: fixed sidebar + scrollable main area.


The admin shell uses a fixed sidebar and independently scrolling main region.

- Sidebar width: `256px` expanded and `68px` collapsed, with collapse state persisted in `localStorage`.
- On mobile, the sidebar is off-canvas and opens from the header menu trigger.
- Main padding: `p-3 sm:p-4 md:p-6 lg:p-8`; app shell height uses `min-h-[100dvh]`.
- Skip link targets the main content; `ErrorBoundary` wraps routed content.

### Auth Layout (`layouts/Auth.vue`)

The desktop layout reserves about half the width for the visual panel and half for the form; the visual panel is hidden below `lg`. The existing page uses an external hero image and decorative gradients. Treat those as current implementation details, not required identity. Keep login usable if any optional image request fails.

### Page Content Convention

Most views use `space-y-6` between major sections. Use this as the default, not a requirement; let the page's task and content determine layout and spacing.
For dashboard metric examples, use values and trends supplied by the backend for the signed-in employee or permitted company scope. Placeholder numbers in documentation examples are illustrative only and must never be copied into live screens.

---


## 8. Components

All shared components live in `src/components/common/` and `src/components/common/form/`.

---

### 8.1 Alert

**File:** `src/components/common/Alert.vue`

Inline dismissible alert banner for success and error states.

#### Props

| Prop | Type | Default | Values |
|------|------|---------|--------|
| `type` | `String` | `"success"` | `"success"` \| `"danger"` |
| `title` | `String` | required | — |
| `message` | `String` | required | — |
| `show` | `Boolean` | `true` | — |

#### Visual Spec

| State | Background | Border | Text |
|-------|-----------|--------|------|
| success | `bg-green-50/50` | `border-green-200` | `text-green-700` |
| danger | `bg-red-50/50` | `border-red-200` | `text-red-700` |

- `rounded-xl p-4 mb-6` with an inline SVG icon (circle-check or circle-x)
- Dismiss button top-right; internal `visible` ref toggled on close
- `role="alert"` + `aria-live="polite"` for screen readers

#### Usage

```vue
<Alert type="danger" title="Login Failed" message="Invalid credentials." :show="hasError" />
<Alert type="success" title="Saved" message="Your changes have been saved." :show="saved" />
```

---

### 8.2 AnimatedValue

**File:** `src/components/common/AnimatedValue.vue`

Inline animated count-up for any numeric value.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `value` | `Number\|String` | `0` | Target number |
| `prefix` | `String` | `""` | Text before number (e.g. `"IDR "`) |
| `suffix` | `String` | `""` | Text after number (e.g. `"%"`) |
| `duration` | `Number` | `800` | Animation duration ms |
| `decimals` | `Number` | `0` | Decimal places |

#### Usage

```vue
<AnimatedValue :value="92" suffix="%" />
<AnimatedValue :value="1234567" prefix="IDR " />
```

Internally powered by `useAnimatedNumber` composable with ease-out cubic curve.

---

### 8.3 StatsCard

**File:** `src/components/common/StatsCard.vue`

Secondary metric card with a theme-aware surface, thin border, value, optional subtitle and tinted icon tile.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `String` | — | Metric label |
| `value` | `String\|Number` | — | Metric value (animates) |
| `subtitle` | `String` | — | Trend or context text |
| `subtitleColor` | `String` | `"text-success"` | Color class for subtitle |
| `iconName` | `String` | — | Lucide icon name string |
| `colorScheme` | `String` | `"blue"` | `blue\|green\|purple\|orange\|red\|yellow\|teal\|cyan\|gray` |
| `loading` | `Boolean` | — | Shows `"..."` while loading |

#### Color Schemes

| Scheme | Icon bg | Icon text |
|--------|---------|-----------|
| blue | `bg-blue-50` | `text-blue-600` |
| green | `bg-green-50` | `text-green-600` |
| purple | `bg-purple-50` | `text-purple-600` |
| orange | `bg-orange-50` | `text-orange-600` |
| red | `bg-red-50` | `text-red-600` |
| yellow | `bg-yellow-50` | `text-yellow-600` |
| teal | `bg-teal-50` | `text-teal-600` |
| cyan | `bg-cyan-50` | `text-cyan-600` |
| gray | `bg-gray-50` | `text-gray-600` |

#### Visual Spec

- Current surface: theme-aware white card with a thin brand border and a tinted semantic icon tile.
- Hover uses a light focus ring; it does not add a colored left stripe.
- Icon schemes exist in code for blue, green, purple, orange, red, yellow, teal, cyan and gray. Prefer inherited HRIS blue, green, orange and purple for metric icons; reserve red/green/amber status colors for actual state.
- Values animate through `useAnimatedNumber`; avoid count-up animation when frequent updates make data harder to read.
- Use `tabular-nums` for money, rates and counts.

#### Usage

```vue
<StatsCard
  title="Active Employees"
  value="142"
  subtitle="+3 this month"
  subtitleColor="text-success"
  iconName="UsersIcon"
  colorScheme="blue"
/>
```

---

### 8.4 MainCard

**File:** `src/components/common/MainCard.vue`

**Dual-mode** component: hero metric card (dark navy gradient) **or** plain white wrapper card.

#### Props (metric mode)

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `title` | `String` | — | Metric label |
| `value` | `String\|Number` | — | Metric value (animates) |
| `subtitle` | `String` | — | Description text |
| `trendLabel` | `String` | — | Badge label e.g. `"+12% this month"` |
| `isTrendUp` | `Boolean` | `true` | Shows `TrendingUp` or `TrendingDown` |
| `iconName` | `String` | — | Lucide icon name string |
| `loading` | `Boolean` | — | Shows `"..."` |

#### Modes

**Metric mode** (no default slot) — renders the navy dark-gradient card:
```vue
<MainCard title="Total Payroll" value="1234567" subtitle="This period" iconName="WalletIcon" />
```

**Wrapper mode** (with default slot) renders a theme-aware surface for custom content:

**Named `footer` slot** available for additional info rows in metric mode.

#### Visual Spec (metric mode)

- `.main-card` class: `background: linear-gradient(266deg, #040724, #0C1448, #040724)`
- `.main-card` class supplies the navy focal background
- `rounded-2xl border border-brand-dark` in metric mode
- All text uses `.text-brand-white*` classes
- Numeric values use `tabular-nums`

---


### 8.5 StatusBadge

**File:** `src/components/common/StatusBadge.vue`

Unified badge for all status / type labels across the app.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `value` | `String` | required | Raw status value |
| `type` | `String` | `"status"` | Badge type (see below) |
| `label` | `String` | `""` | Override display text |

#### Badge Types & Mappings

| `type` | Utility function | Key values |
|--------|-----------------|-----------|
| `status` | `getStatusBadgeClass` | `active`, `inactive`, `growing`, `forming`, `planning`, `dormant` |
| `skill` | `getSkillLevelBadgeClass` | `expert` (purple), `intermediate` (blue), `beginner` (green) |
| `priority` | `getPriorityColor` | `low` (green), `medium` (yellow), `high` (orange), `urgent` (red) |
| `project` | `getProjectStatusColor` | `draft`, `planning`, `active`, `on_hold`, `completed`, `cancelled`, `overdue` |
| `leave-type` | `getLeaveTypeBadgeClass` | `annual`, `sick`, `personal`, `emergency`, `maternity` |
| `leave-status` | `getLeaveRequestStatusBadgeClass` | `pending` (yellow), `approved` (green), `rejected` (red) |
| `task` | `getTaskStatusBadgeClass` | `todo`, `in_progress`, `review`, `done`, `rejected`, `cancelled` |
| `payroll` | `getPayrollStatusColor` | `draft`, `pending`, `approved`, `finalized`, `rejected` |
| `team` | `getStatusColor` | `active`, `forming`, `planning`, `dormant` |

#### Visual Spec

`px-2 py-1 rounded-md text-xs font-semibold capitalize`

#### Usage

```vue
<StatusBadge value="active" type="status" />
<StatusBadge value="high" type="priority" />
<StatusBadge value="finalized" type="payroll" />
<StatusBadge value="in_progress" type="task" label="In Progress" />
```

---

### 8.6 EmptyState

**File:** `src/components/common/EmptyState.vue`

Centered empty state with icon, title, and optional subtitle.

#### Props

| Prop | Type | Default | Values |
|------|------|---------|--------|
| `icon` | `String` | `"Inbox"` | `SearchX\|Users\|Briefcase\|CalendarClock\|FileText\|Inbox` |
| `title` | `String` | `"No data found"` | — |
| `subtitle` | `String` | `""` | — |
| `size` | `String` | `"md"` | `"sm"\|"md"\|"lg"` |

#### Size Variants

| Size | Wrapper padding | Icon size | Title size |
|------|----------------|-----------|-----------|
| `sm` | `py-6` | `w-10 h-10` | `text-sm` |
| `md` | `py-8` | `w-12 h-12` | `text-base` |
| `lg` | `py-12` | `w-16 h-16` | `text-lg font-semibold` |

Has a default slot for optional action buttons.

#### Usage

```vue
<EmptyState icon="Users" title="No employees found" subtitle="Try adjusting your filters." size="lg" />
```

---

### 8.7 SearchFilter

**File:** `src/components/common/SearchFilter.vue`

Combined search input + dynamic dropdown filters bar.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `placeholder` | `String` | `"Search..."` | Input placeholder |
| `filters` | `Array` | `[]` | Filter config array (see below) |
| `showSearchButton` | `Boolean` | `false` | Renders explicit search button |
| `modelValue` | `Object` | `{}` | v-model: `{ search, ...filterKeys }` |

#### Filter Config Shape

```js
{
  key: 'status',          // emitted key
  label: 'All Status',    // placeholder option
  icon: 'CheckCircle',    // icon name: Building|CheckCircle|Briefcase|Tag|Filter
  options: [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
  ]
}
```

#### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `update:modelValue` | `Object` | v-model sync |
| `search` | `Object` | Debounced search params (300ms) |
| `reset` | — | Filters cleared |

#### Visual Spec

- Container: `bg-white border border-brand-border rounded-2xl p-4`
- Input: `rounded-2xl`, focus border `primary-500` with ring
- Dropdowns: `rounded-2xl`, `appearance-none` with custom chevron
- Reset button only shown when `hasActiveFilters` is true

#### Usage

```vue
<SearchFilter
  v-model="filterModel"
  placeholder="Search employees..."
  :filters="[
    { key: 'status', label: 'All Status', icon: 'CheckCircle', options: statusOptions },
    { key: 'department', label: 'All Departments', icon: 'Building', options: deptOptions },
  ]"
  @search="handleSearch"
  @reset="handleReset"
/>
```

---


### 8.8 ModalWrapper

**File:** `src/components/common/ModalWrapper.vue`

General-purpose accessible modal dialog with focus trap and scroll lock.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `show` | `Boolean` | required | Controls visibility |
| `title` | `String` | — | Header title (optional) |
| `maxWidth` | `String` | `"md"` | `sm\|md\|lg\|xl\|2xl\|3xl\|4xl` |

#### Events

| Event | Description |
|-------|-------------|
| `close` | Emitted on backdrop click or Escape key |

#### Slots

| Slot | Description |
|------|-------------|
| `default` | Main scrollable body content |
| `header` | Replaces default title + close button |
| `footer` | Action buttons (not scrollable) |

#### Behavior

- Teleported to `<body>` via `<Teleport>`
- Focus trap: Tab key cycles within modal
- Auto-focuses first focusable element on open
- Body scroll locked (`overflow: hidden`) while open
- Escape key closes
- Backdrop click closes
- Animated: `opacity 200ms ease-out` enter, `150ms ease-in` leave + `scale-95→100` inner

#### Visual Spec

- `bg-white rounded-2xl p-6 max-h-[90vh]`
- Header: `text-brand-dark text-xl font-bold`
- Close button: `X` icon, `text-gray-400 hover:text-gray-600`
- Body: `overflow-y-auto` with thin custom scrollbar
- Backdrop: `bg-black bg-opacity-50 z-[9999]`

#### Usage

```vue
<ModalWrapper :show="isOpen" title="Edit Employee" maxWidth="lg" @close="isOpen = false">
  <template #default>
    <!-- form content -->
  </template>
  <template #footer>
    <button @click="save">Save</button>
  </template>
</ModalWrapper>
```

---

### 8.9 ConfirmationModal

**File:** `src/components/common/ConfirmationModal.vue`

Destructive / warning action confirmation dialog.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `show` | `Boolean` | `false` | Controls visibility |
| `title` | `String` | `"Confirm Action"` | Dialog title |
| `message` | `String` | `"Are you sure…"` | Dialog body text |
| `confirmText` | `String` | `"Confirm"` | CTA button label |
| `cancelText` | `String` | `"Cancel"` | Cancel button label |
| `loading` | `Boolean` | `false` | Shows `"Processing..."` |
| `type` | `String` | `"danger"` | `"danger"\|"warning"\|"info"` |

#### Type Variants

| Type | Icon bg | Icon color | CTA button |
|------|---------|-----------|-----------|
| `danger` | `bg-red-50` | `text-red-600` | Red gradient |
| `warning` | `bg-yellow-50` | `text-yellow-600` | Yellow gradient |
| `info` | `bg-blue-50` | `text-blue-600` | `.blue-gradient` |

#### Events

| Event | Description |
|-------|-------------|
| `confirm` | User clicked confirm |
| `cancel` | User clicked cancel or backdrop |

#### Usage

```vue
<ConfirmationModal
  :show="showDelete"
  title="Delete Employee"
  message="This action cannot be undone."
  confirmText="Delete"
  type="danger"
  :loading="isDeleting"
  @confirm="handleDelete"
  @cancel="showDelete = false"
/>
```

---

### 8.10 ToastContainer

**File:** `src/components/common/ToastContainer.vue`

Global toast notification system. **Place once in `App.vue`.**

Powered by `useToast()` composable — no props needed.

#### Toast Types

| Type | Accent | Icon | Title color |
|------|--------|------|-------------|
| `success` | `bg-green-500` | `CheckCircle` | `text-green-800` |
| `error` | `bg-red-500` | `XCircle` | `text-red-800` |
| `warning` | `bg-yellow-500` | `AlertTriangle` | `text-yellow-800` |
| `info` | `bg-blue-500` | `Info` | `text-blue-800` |

#### Visual Spec

- Fixed `top-6 right-6 z-[9999]`
- `min-width: 360px; max-width: 420px`
- `rounded-2xl border shadow-lg` with left accent bar
- `TransitionGroup` with slide-from-right + scale animation
- Progress bar shrinks over `duration` ms

#### Usage

```js
// In any component or store
import { useToast } from '@/composables/useToast'
const { success, error, warning, info } = useToast()

success('Saved!', 'Employee record updated.')
error('Failed', 'Could not connect to server.')
warning('Warning', 'Unsaved changes will be lost.')
info('Note', 'Changes take effect at next login.')
```

---


### 8.11 Form — Input

**File:** `src/components/common/form/Input.vue`

Standard text / number input with label, optional icon, optional suffix, and error state.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `String` | required | Visible label (also used for `id` generation) |
| `id` | `String` | `""` | Override generated `id` |
| `name` | `String` | `""` | Input `name` attribute |
| `type` | `String` | `"text"` | HTML input type |
| `modelValue` | `String\|Number` | `""` | v-model value |
| `placeholder` | `String` | `""` | Placeholder text |
| `required` | `Boolean` | `false` | HTML required |
| `error` | `String` | `""` | Error message string |
| `min` | `String\|Number` | — | Min value for `type="number"` |
| `step` | `String\|Number` | — | Step for `type="number"` |
| `autocomplete` | `String` | — | HTML autocomplete attribute |

#### Slots

| Slot | Description |
|------|-------------|
| `icon` | Prefix icon (left side) |
| `suffix` | Suffix element (right side, e.g. currency, unit) |

#### States

| State | Border | Ring |
|-------|--------|------|
| Default | `border-gray-200` | — |
| Hover | `hover:border-gray-300` | — |
| Focus | `border-primary-500` | `ring-4 ring-primary-500/10` |
| Error | `border-red-300` | `ring-4 ring-red-500/10` |

- Fixed height `h-12` for consistent alignment
- Error message shown with warning icon below field

#### Usage

```vue
<Input label="Full Name" v-model="form.name" placeholder="Enter name" :error="errors.name">
  <template #icon><UserIcon class="w-4 h-4" /></template>
</Input>
```

---

### 8.12 Form — Select

**File:** `src/components/common/form/Select.vue`

Styled `<select>` with label, optional icon, and error state.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `String` | required | Visible label |
| `id` / `name` | `String` | `""` | Attribute overrides |
| `modelValue` | `String\|Number` | `""` | v-model |
| `placeholder` | `String` | `""` | First `<option>` with empty value |
| `options` | `Array` | `[]` | `[{ value, label }]` |
| `required` | `Boolean` | `false` | — |
| `error` | `String` | `""` | Error message |

#### States

| State | Class |
|-------|-------|
| Default | `border-brand-border` |
| Hover | `hover:ring-2 hover:ring-primary-500/20` |
| Focus | `focus:border-primary-500 focus:ring-primary-500` |
| Error | `border-danger-500 border-2` |

- `appearance-none` with custom SVG chevron
- `rounded-2xl`, `font-semibold`

#### Usage

```vue
<Select
  label="Department"
  v-model="form.department"
  placeholder="Select department"
  :options="[{ value: 'eng', label: 'Engineering' }]"
  :error="errors.department"
>
  <template #icon><BuildingIcon class="w-4 h-4 text-gray-400" /></template>
</Select>
```

---

### 8.13 Form — TextArea

**File:** `src/components/common/form/TextArea.vue`

Multi-line text input with label, optional icon, and error state.

#### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `label` | `String` | required | Visible label |
| `id` / `name` | `String` | `""` | Attribute overrides |
| `modelValue` | `String` | `""` | v-model |
| `rows` | `Number\|String` | `4` | Visible rows |
| `placeholder` | `String` | `""` | — |
| `required` | `Boolean` | `false` | — |
| `error` | `String` | `""` | Error message |

Same border/hover/focus/error states as Select. `rounded-2xl`.

---

### 8.14 SidebarTooltip

**File:** `src/components/ui/SidebarTooltip.vue`

Positional tooltip used in the collapsed sidebar.

#### Props

| Prop | Type | Default | Values |
|------|------|---------|--------|
| `text` | `String` | required | Tooltip label |
| `position` | `String` | `"right"` | `"right"\|"left"\|"top"\|"bottom"` |

- `bg: #0C1C3C`, `color: white`, `border-radius: 8px`
- Arrow pointer via `::before` pseudo-element
- `opacity: 0` default, shown by parent hover logic
- CSS `box-shadow: 0 4px 12px rgba(0,0,0,0.15)`

---


## 9. Navigation

### 9.1 Sidebar

**File:** `src/components/admin/Sidebar.vue`

Collapsible vertical navigation rail with role-gated menu items.

#### States

| State | Width | Behaviour |
|-------|-------|-----------|
| Expanded | `256px` | Icon + text label |
| Collapsed | `68px` | Icon only, CSS tooltip on hover |
| Mobile closed | off-canvas | `-translate-x-full` |
| Mobile open | on-canvas | `translate-x-0` |

Collapsed state persisted in `localStorage` key `"sidebar-collapsed"`.

#### Sections

| Section | Permission gate |
|---------|----------------|
| GENERAL | Per-item `can()` check |
| PERFORMANCE | `can('performance-menu')` |
| SETTINGS | (bottom of nav) |

#### Nav Link Variants

| Variant | Class | Description |
|---------|-------|-------------|
| Inactive | `.nav-link` | White bg, gray icon + text |
| Active | `.nav-link-active` | Dark navy gradient, white icon + text |
| Hover (inactive) | `hover:ring-2 hover:ring-primary-500/20` | Subtle ring highlight |

Both share: `border border-brand-border rounded-2xl transition-all duration-300`

#### Logo

- Concentric circles: outer `primary-100→200`, inner `primary-500→600`
- `BuildingIcon` white in center
- "Team Sync Pro" + "HRIS Dashboard" text hidden when collapsed

#### Collapse Toggle

- Desktop: `PanelLeftIcon` button (`rotate-180` when collapsed), top-right of logo area
- Mobile: `XIcon` closes the sidebar

---

### 9.2 Header

**File:** `src/components/admin/Header.vue`

Top navigation bar with dynamic page title, notifications, and user menu.

#### Sections (left → right)

1. **Mobile menu toggle** — hamburger `MenuIcon`, hidden on `lg+`
2. **Page title block** — dynamic `title` + `subtitle` from route name map
3. **Notification bell** — `BellIcon` with unread count badge
4. **Messages button** — `MessageCircleIcon` (hidden on mobile)
5. **Vertical divider** — `w-px h-8 bg-brand-border`
6. **User profile** — avatar/initials + name + roles + `ChevronDownIcon` + dropdown menu

#### Notification Badge

| Context | Style |
|---------|-------|
| Admin (≤99) | `bg-[#EE2A3B]` red pill, top-right of bell |
| Staff (≤9) | `bg-[#0C51D9]` blue pill, top-right of bell |
| Overflow | Shows `99+` or `9+` |

Unread count polled every **15 seconds**, paused when tab is hidden.

#### Account Dropdown

- `bg-white border border-brand-border rounded-lg shadow-md`
- Links: Profile → `staffMember.profile`, Sign Out (red)
- Closes on outside click

#### Page Title Map

Routes are mapped to `{ title, subtitle }` pairs in a static `titles` object. Unmapped routes fall back to Dashboard.

---


## 10. Composables

All composables live in `src/composables/` and follow the `use` prefix convention.

---

### `useToast`

Global singleton toast queue.

```js
const { success, error, warning, info, addToast, removeToast, toasts } = useToast()

// Shorthand
success('Title', 'Optional message')
error('Title', 'Optional message')

// Full control
addToast({ type: 'info', title: 'Note', message: 'Details', duration: 6000 })
```

- `duration: 0` = persistent (must be dismissed manually)
- Default duration: **4000ms**
- `toasts` is a shared reactive array — `ToastContainer` consumes it

---

### `useConfirmAction`

Modal state machine for destructive actions.

```js
const { isModalOpen, selectedItem, isProcessing, openModal, closeModal, confirmAction } =
  useConfirmAction({
    onSuccess: () => fetchData(),
    onError: (err) => toast.error('Failed', err.message),
  })

// Open with item context
openModal(employee)

// In confirm handler
await confirmAction(async (item) => {
  await store.deleteEmployee(item.id)
})
```

| Return | Type | Description |
|--------|------|-------------|
| `isModalOpen` | `Ref<Boolean>` | Modal visibility |
| `selectedItem` | `Ref<any>` | Item passed to `openModal` |
| `isProcessing` | `Ref<Boolean>` | True while `confirmAction` runs |
| `error` | `Ref<any>` | Last thrown error |
| `openModal(item?)` | Function | Opens modal, sets `selectedItem` |
| `closeModal()` | Function | Closes and resets |
| `confirmAction(cb)` | Function | Runs `cb(selectedItem)` with loading/error handling |

---

### `useDarkMode`

```js
const { isDark, toggle } = useDarkMode()
```

- Reads `localStorage.getItem('theme')`; falls back to `prefers-color-scheme`
- Toggles `dark` class on `<html>` element
- Syncs across tabs via `matchMedia` listener

---

### `useSidebar`

**Provider / consumer pattern** — must be initialised in `Admin.vue` layout:

```js
// In Admin.vue (layout)
const { isOpen, toggleMobile, closeMobile } = provideSidebar()

// In any child component
const { isOpen, isCollapsed, toggleCollapse } = useSidebar()
```

| Return | Type | Description |
|--------|------|-------------|
| `isOpen` | `Ref<Boolean>` | Mobile open state |
| `isCollapsed` | `Ref<Boolean>` | Desktop collapsed state (localStorage) |
| `toggleCollapse()` | Function | Toggle + persist |
| `toggleMobile()` | Function | Toggle mobile overlay |
| `openMobile()` / `closeMobile()` | Function | Explicit control |

---

### `useAnimatedNumber`

Animates a reactive number from its previous value to a new target using ease-out cubic.

```js
const { displayValue } = useAnimatedNumber(myRef, {
  duration: 800,  // ms
  decimals: 0,    // decimal places
})
// displayValue is a Ref<string> — bind directly in template
```

Used internally by `StatsCard`, `MainCard`, and `AnimatedValue`.

---

### `useSearchFilter`

Manages search/filter state + pagination + API call coordination.

```js
const { filters, serverOptions, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } =
  useSearchFilter({
    defaultFilters: { search: null, status: '', department: '' },
    fetchFn: (params) => store.fetchEmployees(params),
    defaultPerPage: 10,
  })
```

| Return | Type | Description |
|--------|------|-------------|
| `filters` | `Ref<Object>` | Current filter values |
| `serverOptions` | `Ref<{page, row_per_page}>` | Pagination state |
| `fetchData()` | Function | Calls `fetchFn` with merged params |
| `handleSearch(newFilters)` | Function | Resets page → 1, merges filters, fetches |
| `handleReset()` | Function | Resets all filters, fetches |
| `handlePageChange(page)` | Function | Updates page, fetches |
| `handlePerPageChange(n)` | Function | Updates per-page, resets page, fetches |

Pair with `<SearchFilter>` component:

```vue
<SearchFilter @search="handleSearch" @reset="handleReset" />
```

---


## 11. Badge & Status Utilities

**File:** `src/utils/badgeUtils.js`

All badge color functions follow the same signature: `fn(value: string) → string (Tailwind classes)`.

### Quick Reference

```js
import {
  getStatusBadgeClass,       // generic active/inactive/growing/forming/planning/dormant
  getSkillLevelBadgeClass,   // expert/intermediate/beginner
  getPriorityColor,          // low/medium/high/urgent
  getProjectStatusColor,     // draft/planning/active/on_hold/completed/cancelled/overdue
  getLeaveTypeBadgeClass,    // annual/sick/personal/emergency/maternity
  getLeaveRequestStatusBadgeClass, // pending/approved/rejected
  getTaskStatusBadgeClass,   // todo/in_progress/review/done/rejected/cancelled
  getPayrollStatusColor,     // draft/pending/approved/finalized/rejected
  getStatusColor,            // team status: active/forming/planning/dormant
  getProgressColor,          // progress %: 0-39 red, 40-59 yellow, 60-79 blue, 80+ green
  TASK_STATUS_ORDER,         // canonical sort order array
  TASK_STATUS_LABELS,        // display labels for task statuses
} from '@/utils/badgeUtils'
```

All functions return a fallback of `"bg-gray-100 text-gray-700"` for unknown values.

### Format Utilities (`src/utils/formatUtils.js`)

```js
import { formatRupiah, formatIDR, formatRupiahCompact, capitalize, getJobStatusText } from '@/utils/formatUtils'

formatRupiah(1500000)        // → "IDR 1.500.000"
formatIDR(1500000)           // → "Rp 1.500.000" (Intl currency)
formatRupiahCompact(1500000) // → "IDR 1.5M"
capitalize('in_progress')    // → "In progress"
getJobStatusText('on_leave') // → "On Leave"
```

---

## 12. Dark Mode

The app has a user-controlled dark theme implemented with CSS custom properties in `src/assets/css/input.css` and `useDarkMode()`. `.main-card` and `.nav-link-active` remain navy focal surfaces in both themes. New or revised UI should use theme-aware tokens rather than isolated `dark:` utility patches.

Dark mode is not yet proven complete across all views. Check both themes whenever a screen or shared component changes, especially literal `bg-white`, `text-gray-*`, borders, charts and status badges.

---

## 13. Accessibility

Accessibility is a requirement to verify, not a blanket status claim. Use semantic controls, visible focus, keyboard behavior and clear state feedback. Check actual contrast pairs in both themes before claiming conformance.

### Required patterns

- Keep skip links and main landmarks in application layouts.
- Mark the active route with `aria-current="page"`, including nested views.
- Give icon-only controls accessible names and visible focus styles.
- For dialogs and mobile navigation, manage Escape, focus entry/return and expanded state.
- Distinguish loading, empty, filtered-empty, permission-denied and error states.
- Never rely on color or a decorative/pulsing dot alone to communicate status.
- Respect `prefers-reduced-motion` and avoid continuous animation for static state.
- Check contrast for rendered text pairs. Normal text needs at least 4.5:1; large text needs at least 3:1.

### Known audit work

Tickets `team-sync-y1k`, `team-sync-cwn`, `team-sync-mc3`, `team-sync-rvs` and `team-sync-e9x` track known contrast, current-route, keyboard-tooltip and mobile-sidebar gaps. Open tickets do not mean the fixes are complete.

---

## 14. Frontend architecture rules

These are implementation conventions, not visual tokens. Follow `team-sync-fe/AGENTS.md` for the full list.

| Avoid | Use |
|-------|-----|
| Options API | Vue `<script setup>` Composition API |
| Axios calls in view components | Pinia store actions |
| `moment` or `dayjs` | Luxon |
| `h-screen` for app shells | `min-h-[100dvh]` |
| Hardcoded theme colors for shared surfaces | Existing CSS custom-property tokens |
| Proportional numerals for data | `tabular-nums` |

---

## Appendix: File Reference

| File | Purpose |
|------|---------|
| `tailwind.config.js` | Tailwind color and type extensions |
| `src/assets/css/input.css` | Brand/theme tokens, gradients, nav and status styles |
| `src/components/common/` | Shared feedback, card, modal, table and state components |
| `src/components/common/form/` | Shared Input, Select and TextArea |
| `src/components/admin/Sidebar.vue` | Permission-filtered app navigation |
| `src/components/admin/Header.vue` | Page title, theme control, notifications and account menu |
| `src/layouts/Admin.vue` | Authenticated application shell |
| `src/layouts/Auth.vue` | Login and account-access shell |
| `src/composables/useDarkMode.js` | User theme preference |
