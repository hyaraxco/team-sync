# Frontend Audit Implementation Plan (v2 - Party Mode Reviewed)

**Status**: ON_GOING  
**Created**: 2026-05-14  
**Reviewed By**: 🏗️ Arsitek, 🧪 Fitri, 🎨 Eka (Party Mode)  
**Owner**: Development Team  
**Related Audits**:
- `docs/audits/frontend-architecture-audit-2026-05-14.md`
- `docs/audits/frontend-ui-ux-audit-2026-05-14.md`

---

## Overview

Implementasi hasil audit FE Team Sync yang mencakup:
- **Architecture**: Code quality, maintainability, security
- **UI/UX**: Accessibility (WCAG 2.2), performance (Core Web Vitals), SEO, best practices

**Total Findings**: 24 issues (2 critical, 7 high, 8 medium, 7 low)

**Party Mode Review Summary**:
- ✅ Security-first priority approved by all personas
- ⚠️ Store/view splitting criteria need definition (Arsitek concern)
- ⚠️ Testing gaps identified (Fitri concern)
- ⚠️ Vue 3 patterns need explicit guidelines (Eka concern)

---

## Vue 3 Best Practices (Added per Eka's recommendation)

All new components MUST follow these patterns:

### Composition API Only
```vue
<script setup>
import { ref, computed } from 'vue'

// Props
const props = defineProps({
  title: { type: String, required: true }
})

// Emits
const emit = defineEmits(['update:modelValue'])

// State
const count = ref(0)

// Computed
const doubled = computed(() => count.value * 2)

// Methods
const increment = () => count.value++
</script>
```

### Composables for Shared Logic
```javascript
// src/composables/useFormValidation.js
export function useFormValidation() {
  const errors = ref({})
  
  const validate = (field, value, rules) => {
    // Validation logic
  }
  
  return { errors, validate }
}
```

### Component Splitting Criteria (Added per Arsitek/Eka consensus)

Split components when:
1. **View > 300 lines** → split by domain section (header, table, actions)
2. **Repeated UI patterns** → extract to `components/common/`
3. **Complex logic > 50 lines** → move to composable
4. **Proven cross-module reuse** → extract to shared component

DO NOT split just for line count — split for **testability and reuse**.

---

## Store Splitting Criteria (Added per Arsitek's recommendation)

Split stores ONLY when:
1. **Proven cross-module reuse** (e.g., `payrollAnalytics` used in dashboard AND payroll view)
2. **Independent test scenarios** (sub-store can be tested in isolation)
3. **Clear domain boundaries** (core vs analytics vs settings)

For stores without proven reuse, extract **composables** within the same file instead.

---

## Phase 1: CRITICAL (Sprint 1, Week 1)

### 1.1 Upgrade Axios (Security CVE)
**Priority**: CRITICAL  
**Effort**: 1 day  
**Owner**: Backend integration lead

**Issue**: Axios 1.12.2 has 16 CVEs (prototype pollution, SSRF, auth bypass)

**Tasks**:
- [ ] Upgrade axios to 1.15.2+ in `team-sync-fe/package.json`
- [ ] Run `bun update axios@^1.15.2`
- [ ] **Add Axios migration test suite** (Fitri's recommendation):
  - [ ] Test network failures (timeout, DNS error, CORS)
  - [ ] Test request cancellation
  - [ ] Test error response parsing (4xx/5xx)
  - [ ] Test request/response interceptors
- [ ] Run all tests: `bun run test` (969 tests must pass)
- [ ] Run E2E: `bun run e2e` (95 tests must pass)
- [ ] Verify no breaking changes in API calls (check all 25 stores)
- [ ] Deploy to staging, smoke test

**Verification**:
```bash
bun audit  # Should show 0 high/critical vulnerabilities
```

**Files Affected**: All 25 stores + `src/plugins/axios.js`

---

### 1.2 Add Security Headers
**Priority**: CRITICAL  
**Effort**: 1 day (increased from 0.5 day per Arsitek's recommendation)  
**Owner**: DevOps / Infrastructure

**Issue**: No CSP, HSTS, X-Frame-Options, Referrer-Policy configured

**Tasks**:
- [ ] Add security headers to Nginx/Apache config
- [ ] **Add Vite dev server headers** (Arsitek's recommendation):
  ```javascript
  // vite.config.js
  export default defineConfig({
    server: {
      headers: {
        'Content-Security-Policy-Report-Only': "default-src 'self'; ...",
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'DENY'
      }
    }
  })
  ```
- [ ] Configure CSP (start with report-only mode)
- [ ] **Add nonce-based CSP for inline scripts** (Arsitek's recommendation):
  ```html
  <script nonce="abc123">
    // Inline script allowed
  </script>
  ```
- [ ] Add HSTS with preload
- [ ] Add X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- [ ] Add Permissions-Policy
- [ ] **Set CSP enforcement timeline** (Arsitek's recommendation): 1 week report-only → enforce
- [ ] Test with https://securityheaders.com
- [ ] Monitor CSP violations for 1 week before enforcing

**Nginx Config**:
```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

# CSP with nonce (production)
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'nonce-RANDOM'; style-src 'self' 'nonce-RANDOM'; img-src 'self' data: https:; connect-src 'self' https://api.teamsync.com; font-src 'self' data:;" always;
```

**Verification**:
```bash
curl -I https://teamsync.com | grep -E "Strict-Transport|X-Content|X-Frame|Referrer|Permissions|Content-Security"
```

---

### 1.3 Add Subresource Integrity (SRI)
**Priority**: CRITICAL (added per Arsitek's recommendation)  
**Effort**: 0.5 day  
**Owner**: Frontend lead

**Issue**: Third-party scripts (if any) loaded without SRI hashes

**Tasks**:
- [ ] Audit all CDN scripts in `index.html` and components
- [ ] Generate SRI hashes for each script
- [ ] Add `integrity` and `crossorigin` attributes
- [ ] Test that scripts still load
- [ ] Document hash generation process

**Implementation**:
```bash
# Generate hash
curl -s https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js | openssl dgst -sha384 -binary | openssl base64 -A
```

```html
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"
        integrity="sha384-[HASH]"
        crossorigin="anonymous"></script>
```

**Note**: If all deps are self-hosted (via bun), this is N/A.

**Verification**: Scripts load successfully with integrity checks

---

## Phase 2: HIGH PRIORITY (Sprint 1-2, Week 2-4)

### 2.1 Replace :focus with :focus-visible
**Priority**: HIGH  
**Effort**: 1 day  
**Owner**: Frontend lead

**Issue**: Focus indicators show on mouse clicks (poor UX)

**Tasks**:
- [ ] Add global `:focus-visible` styles to `src/assets/css/main.css`
- [ ] **Add high contrast mode support** (Eka's recommendation):
  ```css
  @media (prefers-contrast: more) {
    *:focus-visible { outline: 3px solid currentColor; }
  }
  ```
- [ ] Replace all `focus:` Tailwind utilities with `focus-visible:`
- [ ] Test keyboard navigation on all interactive elements
- [ ] Test mouse clicks (should NOT show focus ring)
- [ ] **Test browser compatibility** (Fitri's recommendation): Safari, Firefox, Edge
- [ ] Update Tailwind config if needed

**Implementation**:
```css
/* src/assets/css/main.css */
*:focus:not(:focus-visible) { outline: none; }
*:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: more) {
  *:focus-visible { outline: 3px solid currentColor; }
}
```

**Files to Update**: `src/assets/css/main.css`, all components using `focus:` utilities

**Verification**: Manual keyboard + mouse testing on forms, buttons, links

---

### 2.2 Add Form Error Associations
**Priority**: HIGH  
**Effort**: 2 days  
**Owner**: Frontend lead

**Tasks**:
- [ ] Audit all form components in `src/views/admin/`, `src/views/staff-member/`, `src/components/`
- [ ] Add `aria-invalid` to inputs with errors
- [ ] Add `aria-describedby` linking to error message
- [ ] Add `role="alert"` to error messages
- [ ] Test with screen reader (VoiceOver/NVDA)
- [ ] Update form validation patterns in stores

**Pattern**:
```vue
<input 
  v-model="email" 
  type="email"
  :aria-invalid="!!errors.email"
  :aria-describedby="errors.email ? 'email-error' : undefined"
/>
<span v-if="errors.email" id="email-error" role="alert" class="text-red-600">
  {{ errors.email }}
</span>
```

**Verification**: Screen reader announces errors when validation fails

---

### 2.3 Vendor Bundle Splitting
**Priority**: HIGH  
**Effort**: 1.5 days (increased from 1 day per Arsitek's recommendation)  
**Owner**: Frontend lead

**Issue**: Main vendor bundle is 2.5MB (ApexCharts loaded globally)

**Tasks**:
- [ ] Remove global ApexCharts registration from `src/main.js`
- [ ] Lazy-load ApexCharts in components that use it
- [ ] **Define explicit chunk strategy** (Arsitek's recommendation):
  - `vendor-vue`: vue, pinia, vue-router, axios
  - `vendor-charts`: apexcharts, vue3-apexcharts
  - `vendor-utils`: luxon
  - `vendor-ui`: lucide-vue-next
- [ ] Enable Vite manual chunks in `vite.config.js`
- [ ] Tree-shake Luxon (use named imports only)
- [ ] Build and verify bundle sizes
- [ ] Test all chart components
- [ ] **Test chunk loading failures** (Fitri's recommendation)

**Implementation**:
```javascript
// vite.config.js
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor-vue': ['vue', 'vue-router', 'pinia', 'axios'],
          'vendor-ui': ['lucide-vue-next'],
          'vendor-charts': ['vue3-apexcharts', 'apexcharts'],
          'vendor-utils': ['luxon']
        }
      }
    }
  }
})
```

```vue
<!-- In chart components -->
<script setup>
import { defineAsyncComponent } from 'vue'
const VueApexCharts = defineAsyncComponent(() => 
  import('vue3-apexcharts').then(m => m.default)
)
</script>
```

**Expected Impact**: Reduce initial bundle to ~800KB, improve LCP by 1-2s

**Verification**:
```bash
bun run build
du -h dist/assets/*.js | sort -rh | head -10
```

---

### 2.4 Add API Preconnect
**Priority**: HIGH  
**Effort**: 0.5 day  
**Owner**: Frontend lead

**Issue**: No preconnect to backend API (100-300ms delay on first call)

**Tasks**:
- [ ] Add `<link rel="preconnect">` to `index.html`
- [ ] **Verify CSP connect-src includes API endpoint** (Arsitek's recommendation)
- [ ] Test with Network tab (DNS/TLS should be pre-resolved)
- [ ] Measure first API call latency before/after

**Implementation**:
```html
<!-- index.html -->
<head>
  <link rel="preconnect" href="http://localhost:8000" crossorigin>
  <!-- Production: -->
  <!-- <link rel="preconnect" href="https://api.teamsync.com" crossorigin> -->
</head>
```

**Verification**: Network tab shows preconnect before first API call

---

### 2.5 Split Mega-Stores (Conditional)
**Priority**: HIGH  
**Effort**: 3.5 days (increased from 3 days per Arsitek/Eka consensus)  
**Owner**: Frontend lead

**Issue**: 3 stores exceed 500 lines (payroll 745L, analytics 644L, performanceReview 525L)

**IMPORTANT**: Follow store splitting criteria (see top of document). Split ONLY if proven reuse exists.

**Tasks**:
- [ ] **Evaluate each store for cross-module reuse** (Arsitek's requirement)
- [ ] If reuse proven, split `payroll.js` → `payroll/{core,analytics,settings,reconciliation}.js`
- [ ] If reuse proven, split `analytics.js` → `analytics/{workforce,attendance,leave,payroll,projects,performance}.js`
- [ ] If reuse proven, split `performanceReview.js` → `performance/{reviews,cycles,calibration}.js`
- [ ] If NO reuse, extract composables within same file instead
- [ ] **Add concurrent store access tests** (Fitri's recommendation)
- [ ] Update imports in components
- [ ] Run all tests (969 tests must pass)
- [ ] Update store documentation

**Pattern** (if splitting):
```javascript
// payroll.js (orchestrator)
import { usePayrollCoreStore } from './payroll/core'
import { usePayrollAnalyticsStore } from './payroll/analytics'
import { usePayrollSettingsStore } from './payroll/settings'
import { usePayrollReconciliationStore } from './payroll/reconciliation'

export const usePayrollStore = defineStore('payroll', () => {
    const core = usePayrollCoreStore()
    const analytics = usePayrollAnalyticsStore()
    const settings = usePayrollSettingsStore()
    const reconciliation = usePayrollReconciliationStore()
    
    return { core, analytics, settings, reconciliation }
})
```

**Verification**: All tests pass, no regression in functionality

---

### 2.6 Extract Mega-View Components (Conditional)
**Priority**: HIGH  
**Effort**: 2.5 days (increased from 2 days per Eka's recommendation)  
**Owner**: Frontend lead

**Issue**: 2 views exceed 1500 lines (PayrollDetail 2290L, ReviewDetail 1588L)

**IMPORTANT**: Follow component splitting criteria (see top of document). Split by domain sections, not arbitrary line counts.

**Tasks**:
- [ ] **Identify logical boundaries** (tabs, modals, form sections) (Arsitek's requirement)
- [ ] Extract `PayrollDetail.vue` sections (if boundaries clear):
  - `PayrollDetailHeader.vue`
  - `PayrollDetailSummary.vue`
  - `PayrollDetailTable.vue`
  - `PayrollDetailActions.vue`
- [ ] Extract `ReviewDetail.vue` sections (if boundaries clear):
  - `ReviewDetailHeader.vue`
  - `ReviewDetailSections.vue`
  - `ReviewDetailResponses.vue`
  - `ReviewDetailActions.vue`
- [ ] Update imports and props
- [ ] Run all tests
- [ ] Test E2E flows

**Verification**: Views under 500 lines, all functionality intact

---

### 2.7 Add Automated Accessibility Testing
**Priority**: HIGH (added per Fitri/Eka consensus)  
**Effort**: 1 day  
**Owner**: Frontend lead + QA

**Issue**: No automated a11y testing in CI

**Tasks**:
- [ ] Install axe-core: `bun add -D @axe-core/cli`
- [ ] Add to package.json:
  ```json
  "scripts": {
    "test:a11y": "axe http://localhost:4173 --timeout 30000"
  }
  ```
- [ ] Add to CI workflow (`.github/workflows/fe-guard-smoke.yml`)
- [ ] Set accessibility score threshold (>90)
- [ ] Test all major routes

**Verification**: CI fails if a11y score drops below 90

---

## Phase 3: MEDIUM PRIORITY (Sprint 2-3, Week 5-7)

### 3.1 Add Skip Link
**Priority**: MEDIUM  
**Effort**: 0.5 day

**Tasks**:
- [ ] Add skip link to `AdminLayout.vue` and `StaffMemberLayout.vue`
- [ ] Style with visually-hidden pattern
- [ ] Test keyboard navigation (Tab should focus skip link first)

---

### 3.2 Image Lazy Loading
**Priority**: MEDIUM (moved up per Eka's recommendation)  
**Effort**: 1 day

**Tasks**:
- [ ] Audit all `<img>` tags in components/views
- [ ] Add `loading="lazy"` to below-fold images
- [ ] Keep `loading="eager"` for above-fold (LCP) images
- [ ] **Add WebP/AVIF format support** (Eka's recommendation)
- [ ] Test with Network tab (images should load on scroll)

---

### 3.3 Standardize Loading States
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Audit all stores for loading state patterns
- [ ] Standardize on `loading` and `loadingAnalytics` (if needed)
- [ ] Remove local `ref(false)` loading states in components
- [ ] Update components to use store loading states

---

### 3.4 Create Design System Doc
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Create `docs/references/frontend-design-system.md`
- [ ] Document color palette (primary, secondary, semantic)
- [ ] Document typography scale (headings, body, captions)
- [ ] Document spacing scale (Tailwind's default or custom)
- [ ] Document component patterns (buttons, forms, cards, modals)
- [ ] Document icon usage (Lucide Vue Next conventions)
- [ ] Reference existing `docs/design-system.md` if applicable

---

### 3.5 Color Contrast Audit
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Run Lighthouse accessibility audit
- [ ] Manually check all text/background combinations
- [ ] Fix any contrast ratios below WCAG AA (4.5:1 normal, 3:1 large)
- [ ] Document color combinations in design system doc

---

### 3.6 Add Speculation Rules API
**Priority**: MEDIUM  
**Effort**: 0.5 day

**Tasks**:
- [ ] Add Speculation Rules script to `index.html`
- [ ] Configure `eagerness: "moderate"` (200ms hover)
- [ ] Exclude logout/checkout routes
- [ ] Gate analytics on `document.prerendering`
- [ ] Test navigation speed (Chromium only)

---

### 3.7 Add SEO Meta Tags
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Install `@vueuse/head` or `vue-meta`
- [ ] Add unique title/description per route
- [ ] Add canonical URLs
- [ ] Add Open Graph tags
- [ ] Create `sitemap.xml`
- [ ] Configure `robots.txt`

---

### 3.8 Integrate Error Tracking
**Priority**: MEDIUM (moved up per Fitri's recommendation)  
**Effort**: 1 day

**Tasks**:
- [ ] Choose service (Sentry, Bugsnag, Rollbar)
- [ ] Install SDK
- [ ] Configure global error handler
- [ ] Add unhandled rejection handler
- [ ] Test error reporting
- [ ] Set up alerts

---

### 3.9 Add Performance Regression Testing
**Priority**: MEDIUM (added per Fitri's recommendation)  
**Effort**: 1 day

**Tasks**:
- [ ] Add bundle size tracking
- [ ] Add Lighthouse CI to workflow
- [ ] Set performance budgets (LCP < 2.5s, CLS < 0.1)
- [ ] Fail CI if budgets exceeded

---

## Phase 4: LOW PRIORITY (Backlog)

### 4.1 Target Size Audit
**Effort**: 0.5 day

**Tasks**:
- [ ] Audit all interactive elements
- [ ] Ensure 24×24px minimum (WCAG 2.2 AA)
- [ ] Add `min-width/min-height` CSS

---

### 4.2 Use EmptyState Component
**Effort**: 1 day

**Tasks**:
- [ ] Audit all empty state messages
- [ ] Replace with `EmptyState` component (icon + title + subtitle)
- [ ] Add illustrations if available

---

### 4.3 Standardize Error Handling
**Effort**: 1 day

**Tasks**:
- [ ] Audit all stores for error handling patterns
- [ ] Standardize on `error` state + toast notification
- [ ] Update components to show `<Alert>` for errors

---

### 4.4 Configure Cache Headers
**Effort**: 0.5 day

**Tasks**:
- [ ] Add cache headers to Nginx/Apache config
- [ ] Cache hashed assets for 1 year (immutable)
- [ ] Test with Network tab (cache hits)

---

### 4.5 Add PWA Support (Optional)
**Effort**: 2 days

**Tasks**:
- [ ] Install `vite-plugin-pwa`
- [ ] Configure manifest.json
- [ ] Configure service worker
- [ ] Test offline functionality
- [ ] Test install prompt

**Note**: HRIS apps typically require real-time data. Evaluate if PWA is needed.

---

### 4.6 Audit Passive Event Listeners
**Effort**: 0.5 day

**Tasks**:
- [ ] Search for `addEventListener.*touch|wheel`
- [ ] Add `{ passive: true }` where appropriate
- [ ] Test scroll performance on mobile

---

### 4.7 Add View Transitions API
**Effort**: 0.5 day

**Tasks**:
- [ ] Add `document.startViewTransition` to router
- [ ] Add CSS animations for transitions
- [ ] Test on Chromium (progressive enhancement)

---

### 4.8 Add Error Boundaries
**Priority**: LOW (added per Eka's recommendation)  
**Effort**: 0.5 day

**Tasks**:
- [ ] Create ErrorBoundary component
- [ ] Wrap app root with ErrorBoundary
- [ ] Add fallback UI
- [ ] Test error scenarios

---

## Testing Strategy

### Unit Tests
- All 969 tests must pass after each change
- Add new tests for new patterns (form error associations, etc.)
- **Add regression test suite** (Fitri's requirement)

### E2E Tests
- All 95 Playwright tests must pass
- Add E2E tests for critical flows (payroll, attendance)
- **Add cross-browser testing** (Fitri's requirement): Safari, Firefox, Edge

### Manual Testing
- Keyboard navigation (Tab, Enter, Escape)
- Screen reader (VoiceOver on Mac, NVDA on Windows)
- Mobile responsiveness (Chrome DevTools)
- Color contrast (Lighthouse, manual checks)

### Performance Testing
- Lighthouse audit before/after each phase
- Target: Performance 90+, Accessibility 95+, Best Practices 95+, SEO 90+
- **Add performance regression tests** (Fitri's requirement)

---

## Quality Gates (Added per Fitri's recommendation)

### Pre-Merge Requirements
- [ ] All unit tests pass (969 tests)
- [ ] All E2E tests pass (95 tests)
- [ ] Accessibility score > 90 (axe-core)
- [ ] Performance regression < 10%
- [ ] Bundle size increase < 5%

### Post-Deployment Verification
- [ ] Real User Monitoring (RUM) metrics stable
- [ ] Error rate < 0.1%
- [ ] CSP violation monitoring
- [ ] Performance monitoring (LCP, CLS, INP)

---

## Success Metrics

### Phase 1 (Critical)
- [ ] 0 high/critical vulnerabilities in `bun audit`
- [ ] Security headers score A+ on https://securityheaders.com
- [ ] SRI hashes on all CDN scripts

### Phase 2 (High)
- [ ] Lighthouse Accessibility score 95+
- [ ] Initial bundle size < 1MB
- [ ] LCP < 2.5s (75th percentile)
- [ ] Automated a11y tests in CI

### Phase 3 (Medium)
- [ ] All WCAG 2.2 AA criteria met
- [ ] Lighthouse Performance score 90+
- [ ] SEO score 90+
- [ ] Error tracking integrated

### Phase 4 (Low)
- [ ] All best practices implemented
- [ ] No console errors in production
- [ ] Error boundaries in place

---

## Risks & Mitigations

### Risk 1: Breaking Changes from Axios Upgrade
**Mitigation**: Run full test suite + E2E before deploying + add migration tests

### Risk 2: CSP Breaks Third-Party Scripts
**Mitigation**: Start with report-only mode, monitor violations for 1 week, add nonces

### Risk 3: Bundle Splitting Breaks Chart Components
**Mitigation**: Test all chart components manually + E2E + add chunk loading error handling

### Risk 4: Store Splitting Causes Regression
**Mitigation**: Only split if proven reuse + run all 969 tests + add concurrent access tests

### Risk 5: Over-Abstraction from Premature Splitting
**Mitigation**: Follow splitting criteria strictly — split for reuse, not line count

---

## Rollback Procedures (Added per Fitri's recommendation)

### If Axios Upgrade Breaks Production
1. Revert to 1.12.2 via `bun add axios@1.12.2`
2. Deploy hotfix
3. Investigate breaking changes
4. Re-attempt upgrade with fixes

### If CSP Breaks Production
1. Remove CSP header from Nginx config
2. Reload Nginx
3. Review CSP violation reports
4. Adjust CSP policy
5. Re-enable in report-only mode

### If Bundle Splitting Breaks Production
1. Revert `vite.config.js` changes
2. Rebuild: `bun run build`
3. Deploy previous bundle
4. Review chunk loading errors
5. Fix and re-deploy

---

## Timeline

| Phase | Duration | Completion |
|-------|----------|------------|
| Phase 1 (Critical) | 2.5 days | Week 1 |
| Phase 2 (High) | 12 days | Week 2-4 |
| Phase 3 (Medium) | 8 days | Week 5-7 |
| Phase 4 (Low) | 6.5 days | Backlog |

**Total Estimated Effort**: 29 days (~4 sprints)

**Increased from 24.5 days** due to:
- Security hardening (SRI, nonces, dev headers): +1 day
- Testing requirements (a11y, regression, cross-browser): +2 days
- Splitting criteria evaluation: +0.5 day
- Performance regression testing: +1 day

---

## Party Mode Review Summary

### 🏗️ Arsitek's Verdict
> "Today's shortcut is tomorrow's technical debt — but **tomorrow's over-abstraction is today's technical debt**. Split only when reuse emerges, not preemptively."

**Key Concerns**:
- Store/view splitting may over-abstract without proven reuse
- Security fixes incomplete (missing SRI, nonces, dev headers)
- Bundle splitting needs explicit chunk strategy

**Recommendations Incorporated**: ✅ All

---

### 🧪 Fitri's Verdict
> "It works on your machine is not a test. Add regression suites, edge cases, and quality gates — or expect production fires."

**Key Concerns**:
- No regression test suite
- Missing edge case testing (network failures, chunk loading errors)
- No automated accessibility testing
- No quality gates

**Recommendations Incorporated**: ✅ All

---

### 🎨 Eka's Verdict
> "If the user can't figure it out in 3 seconds, we failed. Focus-visible and form errors are good UX wins — but don't forget image optimization."

**Key Concerns**:
- No Vue 3 Composition API guidelines
- Component splitting criteria too vague
- Missing image optimization
- Effort estimates optimistic

**Recommendations Incorporated**: ✅ All

---

## Next Steps

1. **Review plan with team** — get buy-in on priorities and timeline
2. **Assign owners** — frontend lead, DevOps, QA
3. **Create tickets** — one ticket per task in Phase 1-2
4. **Start with Phase 1** — critical security fixes (Axios, headers, SRI)
5. **Run Lighthouse audit** — establish baseline metrics
6. **Execute phase by phase** — verify after each phase
7. **Archive plan** — move to `docs/plans/archive/` when complete

---

**Plan Status**: ON_GOING  
**Last Updated**: 2026-05-14 (Party Mode Reviewed)  
**Next Review**: After Phase 1 completion
