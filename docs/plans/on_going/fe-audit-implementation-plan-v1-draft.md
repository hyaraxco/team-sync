# Frontend Audit Implementation Plan

**Status**: ON_GOING  
**Created**: 2026-05-14  
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
**Effort**: 0.5 day  
**Owner**: DevOps / Infrastructure

**Issue**: No CSP, HSTS, X-Frame-Options, Referrer-Policy configured

**Tasks**:
- [ ] Add security headers to Nginx/Apache config (or Vite preview server for dev)
- [ ] Configure CSP (start with report-only mode)
- [ ] Add HSTS with preload
- [ ] Add X-Content-Type-Options, X-Frame-Options, Referrer-Policy
- [ ] Add Permissions-Policy
- [ ] Test with https://securityheaders.com
- [ ] Monitor CSP violations for 1 week before enforcing

**Nginx Config**:
```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

# CSP (adjust for your needs)
add_header Content-Security-Policy-Report-Only "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' http://localhost:8000; font-src 'self' data:; report-uri /csp-report" always;
```

**Verification**:
```bash
curl -I https://teamsync.com | grep -E "Strict-Transport|X-Content|X-Frame|Referrer|Permissions|Content-Security"
```

---

## Phase 2: HIGH PRIORITY (Sprint 1-2, Week 2-3)

### 2.1 Replace :focus with :focus-visible
**Priority**: HIGH  
**Effort**: 1 day  
**Owner**: Frontend lead

**Issue**: Focus indicators show on mouse clicks (poor UX)

**Tasks**:
- [ ] Add global `:focus-visible` styles to `src/assets/css/main.css`
- [ ] Replace all `focus:` Tailwind utilities with `focus-visible:`
- [ ] Test keyboard navigation on all interactive elements
- [ ] Test mouse clicks (should NOT show focus ring)
- [ ] Update Tailwind config if needed

**Implementation**:
```css
/* src/assets/css/main.css */
*:focus {
  outline: none;
}

*:focus-visible {
  outline: 2px solid currentColor;
  outline-offset: 2px;
}
```

**Files to Update**: `src/assets/css/main.css`, all components using `focus:` utilities

**Verification**: Manual keyboard + mouse testing on forms, buttons, links

---

### 2.2 Add Form Error Associations
**Priority**: HIGH  
**Effort**: 2 days  
**Owner**: Frontend lead

**Issue**: No `aria-invalid` or `aria-describedby` on form inputs

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
**Effort**: 1 day  
**Owner**: Frontend lead

**Issue**: Main vendor bundle is 2.5MB (ApexCharts loaded globally)

**Tasks**:
- [ ] Remove global ApexCharts registration from `src/main.js`
- [ ] Lazy-load ApexCharts in components that use it
- [ ] Enable Vite manual chunks in `vite.config.js`
- [ ] Tree-shake Luxon (use named imports only)
- [ ] Build and verify bundle sizes
- [ ] Test all chart components

**Implementation**:
```javascript
// vite.config.js
export default defineConfig({
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor-vue': ['vue', 'vue-router', 'pinia'],
          'vendor-ui': ['lucide-vue-next'],
          'vendor-charts': ['vue3-apexcharts', 'apexcharts'],
          'vendor-utils': ['axios', 'luxon']
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

### 2.5 Add Subresource Integrity (SRI)
**Priority**: HIGH  
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

### 2.6 Split Mega-Stores
**Priority**: HIGH  
**Effort**: 3 days  
**Owner**: Frontend lead

**Issue**: 3 stores exceed 500 lines (payroll 745L, analytics 644L, performanceReview 525L)

**Tasks**:
- [ ] Split `payroll.js` → `payroll/{core,analytics,settings,reconciliation}.js`
- [ ] Split `analytics.js` → `analytics/{workforce,attendance,leave,payroll,projects,performance}.js`
- [ ] Split `performanceReview.js` → `performance/{reviews,cycles,calibration}.js`
- [ ] Update imports in components
- [ ] Run all tests (969 tests must pass)
- [ ] Update store documentation

**Pattern**:
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

### 2.7 Extract Mega-View Components
**Priority**: HIGH  
**Effort**: 2 days  
**Owner**: Frontend lead

**Issue**: 2 views exceed 1500 lines (PayrollDetail 2290L, ReviewDetail 1588L)

**Tasks**:
- [ ] Extract `PayrollDetail.vue` sections into components:
  - `PayrollDetailHeader.vue`
  - `PayrollDetailSummary.vue`
  - `PayrollDetailTable.vue`
  - `PayrollDetailActions.vue`
- [ ] Extract `ReviewDetail.vue` sections into components:
  - `ReviewDetailHeader.vue`
  - `ReviewDetailSections.vue`
  - `ReviewDetailResponses.vue`
  - `ReviewDetailActions.vue`
- [ ] Update imports and props
- [ ] Run all tests
- [ ] Test E2E flows

**Verification**: Views under 500 lines, all functionality intact

---

## Phase 3: MEDIUM PRIORITY (Sprint 2-3, Week 4-6)

### 3.1 Add Skip Link
**Priority**: MEDIUM  
**Effort**: 0.5 day

**Tasks**:
- [ ] Add skip link to `AdminLayout.vue` and `StaffMemberLayout.vue`
- [ ] Style with visually-hidden pattern
- [ ] Test keyboard navigation (Tab should focus skip link first)

---

### 3.2 Image Lazy Loading
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Audit all `<img>` tags in components/views
- [ ] Add `loading="lazy"` to below-fold images
- [ ] Keep `loading="eager"` for above-fold (LCP) images
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
**Priority**: MEDIUM  
**Effort**: 1 day

**Tasks**:
- [ ] Choose service (Sentry, Bugsnag, Rollbar)
- [ ] Install SDK
- [ ] Configure global error handler
- [ ] Add unhandled rejection handler
- [ ] Test error reporting
- [ ] Set up alerts

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

## Testing Strategy

### Unit Tests
- All 969 tests must pass after each change
- Add new tests for new patterns (form error associations, etc.)

### E2E Tests
- All 95 Playwright tests must pass
- Add E2E tests for critical flows (payroll, attendance)

### Manual Testing
- Keyboard navigation (Tab, Enter, Escape)
- Screen reader (VoiceOver on Mac, NVDA on Windows)
- Mobile responsiveness (Chrome DevTools)
- Color contrast (Lighthouse, manual checks)

### Performance Testing
- Lighthouse audit before/after each phase
- Target: Performance 90+, Accessibility 95+, Best Practices 95+, SEO 90+

---

## Success Metrics

### Phase 1 (Critical)
- [ ] 0 high/critical vulnerabilities in `bun audit`
- [ ] Security headers score A+ on https://securityheaders.com

### Phase 2 (High)
- [ ] Lighthouse Accessibility score 95+
- [ ] Initial bundle size < 1MB
- [ ] LCP < 2.5s (75th percentile)

### Phase 3 (Medium)
- [ ] All WCAG 2.2 AA criteria met
- [ ] Lighthouse Performance score 90+
- [ ] SEO score 90+

### Phase 4 (Low)
- [ ] All best practices implemented
- [ ] No console errors in production

---

## Risks & Mitigations

### Risk 1: Breaking Changes from Axios Upgrade
**Mitigation**: Run full test suite + E2E before deploying

### Risk 2: CSP Breaks Third-Party Scripts
**Mitigation**: Start with report-only mode, monitor violations for 1 week

### Risk 3: Bundle Splitting Breaks Chart Components
**Mitigation**: Test all chart components manually + E2E

### Risk 4: Store Splitting Causes Regression
**Mitigation**: Run all 969 tests, manual testing of affected features

---

## Timeline

| Phase | Duration | Completion |
|-------|----------|------------|
| Phase 1 (Critical) | 1.5 days | Week 1 |
| Phase 2 (High) | 10 days | Week 2-3 |
| Phase 3 (Medium) | 7 days | Week 4-6 |
| Phase 4 (Low) | 6 days | Backlog |

**Total Estimated Effort**: 24.5 days (~3-4 sprints)

---

## Next Steps

1. **Review plan with team** — get buy-in on priorities
2. **Assign owners** — frontend lead, DevOps, etc.
3. **Create tickets** — one ticket per task in Phase 1-2
4. **Start with Phase 1** — critical security fixes
5. **Run Lighthouse audit** — establish baseline metrics
6. **Execute phase by phase** — verify after each phase
7. **Archive plan** — move to `docs/plans/archive/` when complete

---

**Plan Status**: ON_GOING  
**Last Updated**: 2026-05-14  
**Next Review**: After Phase 1 completion
