# Team Sync — Frontend Design System

> **Stack:** Vue 3 · Vite 7 · Tailwind CSS · Plus Jakarta Sans · Lucide Vue Next
> **Last updated:** 2026-05-16

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
14. [Anti-Patterns](#14-anti-patterns)

---


## 1. Design Tokens

All tokens live in two places: `tailwind.config.js` (extended theme) and `src/assets/css/input.css` (global CSS classes).

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
| Brand Light | `#6B7280` | `text-brand-light` / `bg-brand-light` | Secondary / muted text |
| Brand Border | `#DCDEDD` | `border-brand-border` | All card / input borders |
| Brand Primary | `#0C51D9` | `border-brand-primary` / `text-brand-primary` | Focus rings, interactive borders |
| Brand White | `#FFFFFF` | `.text-brand-white` | Text on dark surfaces |
| Brand White 90 | `rgba(255,255,255,0.9)` | `.text-brand-white-90` | Card titles on dark bg |
| Brand White 80 | `rgba(255,255,255,0.8)` | `.text-brand-white-80` | Card subtitles on dark bg |
| Brand White 70 | `rgba(255,255,255,0.7)` | `.text-brand-white-70` | Tertiary on dark bg |
| Success | `#059669` | `.text-success` / `bg-success-*` | Positive trends |
| Danger | `#DC2626` | `.text-danger` / `bg-danger-*` | Error states |
| Warning | (amber scale) | `bg-warning-*` | Warning states |

### Gradient Tokens

| Name | Value | Class |
|------|-------|-------|
| Blue Gradient | `linear-gradient(265.5deg, #0C51D9 5.45%, #6F96E3 52%, #0C51D9 100.36%)` | `.blue-gradient` |
| Dark Card Gradient | `linear-gradient(266deg, #040724 5.45%, #0C1448 52%, #040724 100.36%)` | `.main-card` |
| Auth Left Panel | `linear-gradient(to br, #0C51D9/90 → #1a3a6e/85 → #0a1f44/95)` | inline in `Auth.vue` |

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

---

## 3. Color System

### Semantic Colors

| Intent | Tailwind |
|--------|---------|
| Background (app) | `bg-gray-50` |
| Surface (card) | `bg-white` |
| Border | `border-brand-border` |
| Text primary | `text-brand-dark` (`#0C1C3C`) |
| Text muted | `text-brand-light` (`#6B7280`) |
| Nav link bg | `.nav-link` (white) |
| Nav link active | `.nav-link-active` (dark navy gradient) |

> **Note:** Dark mode `dark:` classes have been removed. The `useDarkMode` composable and Tailwind `darkMode: 'class'` config remain for future full dark mode implementation.

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

### Border Radius

All arbitrary `rounded-[Npx]` values have been standardized to Tailwind defaults:

| Component | Radius | Tailwind |
|-----------|--------|---------|
| Cards (all) | `20px` | `rounded-2xl` |
| Inputs, Selects, Textareas | `16px` | `rounded-2xl` |
| Buttons | `8px` | `rounded-lg` |
| Icon containers (large) | `20px` | `rounded-2xl` |
| Icon containers (small) | `12px` | `rounded-xl` |
| Avatar / user photo | full circle | `rounded-full` |
| Badges | `6px` | `rounded-md` |
| Tooltip | `8px` | inline style |
| Notification panel items | `16px` | `rounded-2xl` |

> **Anti-pattern:** Do not use `rounded-[Npx]` arbitrary values. Use Tailwind's built-in scale (`rounded-lg`, `rounded-xl`, `rounded-2xl`, `rounded-3xl`).

---

## 5. Elevation & Shadows

| Layer | Value | Usage |
|-------|-------|-------|
| Card default | `border border-brand-border` | All white cards |
| Card hover | `hover:ring-2 hover:ring-primary-500/20` | StatsCard, SearchFilter inputs |
| StatsCard left border | `border-l-4 border-l-{color}-500` | Color-coded accent matching `colorScheme` |
| Dark card (MainCard) | `box-shadow: -2px 2px 1px 0 #1A2570 inset, 2px 2px 1px 0 rgba(26,37,112,0.55) inset` | `.main-card` |
| Modal backdrop | `bg-black bg-opacity-50` | `ModalWrapper` |
| Confirmation backdrop | `backdrop-blur-sm bg-black/30` | `ConfirmationModal` |
| Toast | `shadow-lg shadow-black/5` | `ToastContainer` |
| Dropdown menu | `shadow-md` | Header account menu |
| Tooltip | `box-shadow: 0 4px 12px rgba(0,0,0,0.15)` | `SidebarTooltip` |

> **Anti-pattern:** Do not use `hover:border-2` — it causes 1px layout shift. Use `hover:ring-2` instead.
> **Anti-pattern:** Do not use arbitrary `shadow-[...]` values. Use Tailwind defaults (`shadow-sm`, `shadow-md`, `shadow-lg`, `shadow-xl`).

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

```
┌─────────────────────────────────────────────┐
│  Sidebar (256px / 68px collapsed)           │
│  ┌─────────────────────────────────────────┐ │
│  │  Header (border-b, bg-white)            │ │
│  ├─────────────────────────────────────────┤ │
│  │  <main> p-3 sm:p-4 md:p-6 lg:p-8       │ │
│  │  <RouterView />                          │ │
│  └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

- **Sidebar width:** `256px` expanded · `68px` collapsed (persisted in `localStorage`)
- **Mobile:** sidebar is off-canvas (`-translate-x-full`), opened by a hamburger button; black overlay `bg-black/30` shown behind
- **Main bg:** `bg-gray-50`
- **Sidebar bg:** `bg-white`
- **Layout height:** `min-h-[100dvh]` (NOT `h-screen` — iOS Safari viewport bug)
- **Skip-link:** `<a href="#main-content">Skip to main content</a>` for keyboard accessibility
- **ErrorBoundary:** wraps `<RouterView />` in all 3 layouts (Admin, Auth, StaffMemberCreateLayout)

### Auth Layout (`layouts/Auth.vue`)

Split-screen 50/50 layout (hidden left panel on mobile):

```
┌──────────────────────┬──────────────────────┐
│  Left panel (50%)    │  Right panel (50%)   │
│  bg-gray-900         │  flex items-center   │
│  Blue gradient       │  justify-center       │
│  overlay + image     │  <RouterView />       │
│  Brand copy + stats  │                       │
└──────────────────────┴──────────────────────┘
```

- Left panel hidden below `lg` breakpoint
- Right panel: `min-h-screen`, `bg-gradient-to-br from-slate-50 via-white to-blue-50`

### Page Content Convention

Every view wraps its content in `<div class="space-y-6">` to maintain consistent vertical rhythm between sections.

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

Secondary metric card — white background, colored icon container.

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

- `bg-white border border-brand-border rounded-2xl`
- Hover: `hover:ring-2 hover:ring-primary-500/20`
- Left accent: `border-l-4 border-l-{colorScheme}-500` (color-coded per scheme)
- Value animates from 0 → target with ease-out cubic
- Numeric values use `tabular-nums` for proper alignment

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

**Wrapper mode** (with default slot) — renders a plain white card:
```vue
<MainCard>
  <h3>Custom content</h3>
</MainCard>
```

**Named `footer` slot** available for additional info rows in metric mode.

#### Visual Spec (metric mode)

- `.main-card` class: `background: linear-gradient(266deg, #040724, #0C1448, #040724)`
- Inset box-shadow for depth
- `rounded-2xl border border-gray-800`
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

> **Status:** Partially scaffolded but NOT active. All `dark:` utility classes have been removed from components. The infrastructure remains for future full implementation.

### Infrastructure (in place)

- `useDarkMode()` composable toggles `dark` class on `<html>` + persists in `localStorage`
- `tailwind.config.js` has `darkMode: 'class'`
- `.nav-link-active` and `.main-card` are dark navy by design — work on both themes

### Current State

- **Zero `dark:` classes** in any `.vue` file
- Dark mode toggle exists but produces no visual change
- Future: when dark mode is fully implemented, add `dark:` classes systematically using design tokens

---


## 13. Accessibility

The project follows WCAG 2.1 AA practices throughout.

### Focus Management

| Pattern | Implementation |
|---------|---------------|
| Skip link | `<a href="#main-content">Skip to main content</a>` — visually hidden, shown on focus |
| Modal focus trap | `ModalWrapper` cycles Tab within focusable elements |
| Modal auto-focus | First focusable element receives focus on open |
| Escape key | Closes `ModalWrapper` |
| Click outside | Closes `ModalWrapper` and `ConfirmationModal` |

### ARIA

| Component | ARIA attributes |
|-----------|----------------|
| `ModalWrapper` | `role="dialog" aria-modal="true" aria-labelledby="{titleId}"` |
| `ConfirmationModal` | `role="dialog" aria-modal="true"` |
| `Alert` | `role="alert" aria-live="polite"` |
| `SearchFilter` inputs | `aria-label` on each input/select |
| Header bell | `aria-label`, `aria-haspopup="dialog"`, `aria-expanded`, `aria-controls` |
| Header profile | `aria-label`, `aria-haspopup="menu"`, `aria-expanded`, `aria-controls` |
| Account menu items | `role="menuitem"` |
| Sidebar nav links | `aria-current="page"` on active route |
| Sidebar toggle | `aria-label="Toggle sidebar"` |
| Toast dismiss | `aria-label="Dismiss notification"` |
| Form inputs | `<label :for="fieldId">` — `id` auto-generated from label text |

### Keyboard Navigation

- All interactive elements reachable by keyboard
- Focus rings visible on all buttons/inputs (Tailwind `focus:ring-*`)
- `prefers-reduced-motion` respected in `Auth.vue` shell

### Colour Contrast

All text/background combinations use Tailwind's built-in palette at sufficient contrast:
- Body text (`#0C1C3C`) on white: **AAA**
- Muted text (`#6B7280`) on white: **AA**
- White text on `#0C51D9`: **AA**
- Badge text on badge background: verified per color pair

---

## 14. Anti-Patterns

These patterns **must not** be used in this project:

| ❌ Don't | ✅ Do instead |
|----------|--------------|
| Options API (`export default { data() {} }`) | `<script setup>` Composition API always |
| TypeScript files (`.ts`, `.tsx`) | JavaScript (`.js`, `.vue`) |
| Call Axios from components | Dispatch Pinia store actions |
| `npm install` | `bun install` |
| `npm run *` | `bun run *` |
| Custom CSS classes | Tailwind utilities |
| `moment` or `dayjs` | `Luxon` |
| Custom chart library | `VueApexCharts` (globally registered) |
| Multiple stores per domain | One Pinia store per domain (25 total) |
| Put unit tests in `docs/` | `src/tests/{role}/` mirroring views |
| Hardcode route names in components | Use named routes from `src/router/` |
| Bypass `permissionAccess.js` | Use `meta.requiredPermission` on routes |
| Role string checks | `can()` / `canOneOf()` from `permissionHelper.js` |
| `npm` lockfile (`package-lock.json` in FE) | `bun.lock` only |
| Mix `@/` alias inconsistently | Always use `@/` → `src/` alias |
| Inline `style=` for colours | Tailwind classes (or CSS custom classes for brand tokens) |
| `h-screen` for full-height layouts | `min-h-[100dvh]` (iOS Safari viewport bug) |
| `hover:border-2` for hover states | `hover:ring-2 hover:ring-primary-500/20` (no layout shift) |
| `dark:` classes on components | Remove — dark mode not yet fully implemented |
| `rounded-[Npx]` arbitrary radius | `rounded-lg`, `rounded-xl`, `rounded-2xl`, `rounded-3xl` |
| `shadow-[...]` arbitrary shadows | `shadow-sm`, `shadow-md`, `shadow-lg`, `shadow-xl` |
| `border-[#DCDEDD]` hardcoded border | `border-brand-border` (Tailwind config token) |
| `focus:ring-[#0C51D9]` hardcoded focus | `focus:ring-primary-500` (Tailwind config token) |
| Proportional font for numbers | `tabular-nums` class on financial/data values |

---

## Appendix: File Reference

| File | Purpose |
|------|---------|
| `tailwind.config.js` | Design tokens: primary, brand-dark/light/border/primary, success/danger/warning, border radius, animations |
| `src/assets/css/input.css` | Global brand tokens, gradient classes, nav classes, badge classes, tooltips |
| `src/components/common/` | 11 shared UI components (Alert, AnimatedValue, ConfirmationModal, EmptyState, ErrorBoundary, MainCard, ModalWrapper, SearchFilter, StatsCard, StatusBadge, ToastContainer) |
| `src/components/common/form/` | Input, Select, TextArea |
| `src/components/ui/` | SidebarTooltip |
| `src/components/admin/Sidebar.vue` | Navigation rail |
| `src/components/admin/Header.vue` | Top bar |
| `src/layouts/Admin.vue` | Two-column app shell (ErrorBoundary wraps RouterView) |
| `src/layouts/Auth.vue` | Split-screen auth shell (ErrorBoundary wraps RouterView) |
| `src/layouts/StaffMemberCreateLayout.vue` | Staff creation wizard (ErrorBoundary wraps RouterView) |
| `src/composables/` | 6 reusable logic hooks |
| `src/utils/badgeUtils.js` | All status → CSS class mappings |
| `src/utils/formatUtils.js` | Number, currency, string formatters |
| `src/helpers/permissionHelper.js` | `can()`, `canOneOf()` permission checks |
| `src/helpers/errorHelper.js` | `handleError()` — extracts user-friendly error messages |
