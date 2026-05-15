# Frontend Architecture & Code Quality Audit
**Team Sync FE (Vue 3 SPA)**  
**Date**: 2026-05-14  
**Auditor**: Oracle (enowX Labs AI)  
**Scope**: Architecture, security, code quality, maintainability, performance

---

## Executive Summary

**Overall Assessment**: **GOOD** with critical security vulnerabilities requiring immediate attention.

The codebase demonstrates strong architectural discipline:
- ✅ Consistent Composition API usage (no Options API violations)
- ✅ Clean separation of concerns (stores handle API, components handle UI)
- ✅ Comprehensive test coverage (969 tests passing, 127 test files)
- ✅ No XSS vulnerabilities (no `v-html`, `innerHTML`, or `eval` usage)
- ✅ Proper error handling patterns (281 try-catch blocks in stores)
- ✅ Memory leak prevention (event listeners properly cleaned up)

**Critical Issues**: 1 (dependency vulnerabilities)  
**High-Priority**: 4 (architecture smells, maintainability gaps)  
**Medium-Priority**: 6 (refactoring opportunities)

---

## 1. CRITICAL ISSUES

### 1.1 Axios Dependency Vulnerabilities ⚠️ **URGENT**

**Severity**: CRITICAL  
**Impact**: Prototype pollution, SSRF, credential injection, authentication bypass

**Current Version**: `axios@1.12.2`  
**Vulnerable to**: 16 CVEs including:
- **GHSA-q8qp-cvcw-x6jj** (HIGH, CVSS 7.4): Prototype pollution read-side gadgets allowing credential injection
- **GHSA-pmwg-cvhr-8vh7** (HIGH, CVSS 7.2): NO_PROXY bypass via RFC 1122 loopback subnet
- **GHSA-pf86-5x62-jrwf** (HIGH, CVSS 7.4): Prototype pollution gadgets enabling response tampering
- **GHSA-6chq-wfr3-2hj9** (HIGH, CVSS 7.4): Header injection via prototype pollution
- **GHSA-43fc-jf86-j433** (HIGH, CVSS 7.5): DoS via `__proto__` key in mergeConfig

**Recommendation**:
```bash
bun update axios@^1.15.2
```

**Verification**:
```bash
bun audit
bun run test  # Ensure no breaking changes
```

**Files Affected**: All 25 stores + `src/plugins/axios.js` + 1 component (`UpcomingCutiBersama.vue`)

---

## 2. HIGH-PRIORITY IMPROVEMENTS

### 2.1 Store File Size — Maintainability Risk

**Issue**: Three stores exceed 500 lines, violating single-responsibility principle.

| Store | Lines | Complexity |
|-------|-------|------------|
| `payroll.js` | 745 | 100+ actions, 20+ state properties |
| `analytics.js` | 644 | 50+ state properties, 30+ actions |
| `performanceReview.js` | 525 | Complex nested state, 25+ actions |

**Impact**:
- Hard to test (large surface area)
- Difficult to reason about state flow
- High cognitive load for new developers
- Merge conflicts likely

**Recommendation**: Split into domain-specific sub-stores using Pinia composition pattern.

**Example Refactor** (`payroll.js`):
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

**Files to Split**:
- `src/stores/payroll.js` → `payroll/{core,analytics,settings,reconciliation}.js`
- `src/stores/analytics.js` → `analytics/{workforce,attendance,leave,payroll,projects,performance}.js`
- `src/stores/performanceReview.js` → `performance/{reviews,cycles,calibration}.js`

---

### 2.2 View File Size — Component Extraction Needed

**Issue**: Two views exceed 1500 lines, making them hard to maintain and test.

| View | Lines | Issue |
|------|-------|-------|
| `PayrollDetail.vue` | 2290 | Monolithic, 80+ reactive refs, 20+ computed, 30+ methods |
| `ReviewDetail.vue` | 1588 | Complex state management, nested modals |

**Impact**:
- Slow IDE performance
- Hard to isolate bugs
- Difficult to test edge cases
- Poor code reusability

**Recommendation**: Extract logical sections into child components.

**Example** (`PayrollDetail.vue`):
```vue
<!-- PayrollDetail.vue (orchestrator) -->
<template>
    <div>
        <PayrollDetailHeader :payroll="payroll" @action="handleAction" />
        <PayrollDetailTabs v-model="activeTab" />
        <PayrollEmployeeList v-if="activeTab === 'employees'" />
        <PayrollActivityLog v-if="activeTab === 'activity'" />
        <PayrollReconciliation v-if="activeTab === 'reconciliation'" />
        <PayrollApprovals v-if="activeTab === 'approvals'" />
    </div>
</template>
```

**Components to Extract**:
- `PayrollDetailHeader.vue` (statistics, actions)
- `PayrollEmployeeList.vue` (table, filters, pagination)
- `PayrollActivityLog.vue` (timeline)
- `PayrollReconciliation.vue` (exceptions, resolution modal)
- `PayrollApprovals.vue` (approval matrix, decision form)

---

### 2.3 API Call in Component — Architecture Violation

**Issue**: `UpcomingCutiBersama.vue` directly imports and calls `axiosInstance`, violating the "API calls only in stores" rule.

**Location**: `src/components/staff-member/UpcomingCutiBersama.vue:4,15`

```javascript
// ❌ CURRENT (violates architecture)
import { axiosInstance } from "@/plugins/axios";

const fetchUpcomingCutiBersama = async () => {
    const response = await axiosInstance.get("/my-upcoming-cuti-bersama");
    cutiBersama.value = response.data.data || [];
};
```

**Recommendation**: Move to `holidayCalendar` store.

```javascript
// ✅ CORRECT
// src/stores/holidayCalendar.js
actions: {
    async fetchUpcomingCutiBersama() {
        this.loading = true;
        try {
            const response = await axiosInstance.get("/my-upcoming-cuti-bersama");
            this.upcomingCutiBersama = response.data.data || [];
        } catch (error) {
            this.error = handleError(error);
        } finally {
            this.loading = false;
        }
    }
}

// src/components/staff-member/UpcomingCutiBersama.vue
import { useHolidayCalendarStore } from "@/stores/holidayCalendar";

const holidayStore = useHolidayCalendarStore();
onMounted(() => holidayStore.fetchUpcomingCutiBersama());
```

---

### 2.4 Missing Security Headers

**Issue**: `index.html` lacks CSP, HSTS, and other security headers.

**Current State**:
```html
<!-- index.html -->
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- No CSP, no security headers -->
</head>
```

**Recommendation**: Add security headers via server configuration (not meta tags for production).

**Vite Dev Server** (`vite.config.js`):
```javascript
export default defineConfig({
    server: {
        headers: {
            'Content-Security-Policy': "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' http://localhost:8000;",
            'X-Content-Type-Options': 'nosniff',
            'X-Frame-Options': 'DENY',
            'Referrer-Policy': 'strict-origin-when-cross-origin',
        }
    }
})
```

**Production** (Nginx/Apache):
```nginx
# nginx.conf
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://api.teamsync.com;" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

**Note**: Current Google Fonts usage requires `unsafe-inline` for styles. Consider self-hosting fonts to tighten CSP.

---

## 3. MEDIUM-PRIORITY REFACTORS

### 3.1 Redundant Getters in Stores

**Issue**: 4 stores define trivial getters that just return state properties.

**Example** (`option.js`):
```javascript
// ❌ REDUNDANT
getters: {
    getTaskPriorities: (state) => state.taskPriorities,
    getTaskStatuses: (state) => state.taskStatuses,
    getSkillLevels: (state) => state.skillLevels,
}

// ✅ SIMPLER (use storeToRefs)
import { storeToRefs } from 'pinia';
const { taskPriorities, taskStatuses, skillLevels } = storeToRefs(useOptionStore());
```

**Recommendation**: Remove trivial getters from:
- `src/stores/option.js` (3 getters)
- `src/stores/auth.js` (`token` getter — use `Cookies.get('token')` directly)
- `src/stores/setup.js`
- `src/stores/notifications.js`

**Keep getters only for**:
- Computed/derived state (e.g., `unreadCount`)
- Complex transformations
- Filtered/sorted data

---

### 3.2 Index as Key Anti-Pattern

**Issue**: 6 instances use array index as `:key` in `v-for`, breaking Vue's reactivity assumptions.

**Locations**:
- `src/views/admin/project/ProjectDetail.vue` (paragraph rendering)
- `src/views/admin/project/ProjectEdit.vue` (team member chips)
- `src/views/admin/project/ProjectCreate.vue` (team member chips)
- `src/views/admin/UpgradePlan.vue` (plan features, 2 instances)
- `src/views/staff-member/StaffMemberTeam.vue` (member list)

**Problem**:
```vue
<!-- ❌ BAD: Index as key breaks reactivity when list reorders -->
<li v-for="(feature, index) in plan.features" :key="index">
```

**Recommendation**:
```vue
<!-- ✅ GOOD: Use stable unique identifier -->
<li v-for="feature in plan.features" :key="feature.id || feature.name">

<!-- If no unique field exists, generate stable keys -->
<li v-for="(feature, index) in plan.features" :key="`${plan.id}-feature-${index}`">
```

**Impact**: Low (these lists are static/append-only), but violates Vue best practices.

---

### 3.3 Duplicate Error Handling Logic

**Issue**: 281 try-catch blocks in stores follow identical pattern — opportunity for DRY.

**Current Pattern** (repeated 281 times):
```javascript
async fetchSomething() {
    this.loading = true;
    this.error = null;
    try {
        const response = await axiosInstance.get('/endpoint');
        this.data = response.data.data;
    } catch (error) {
        this.error = handleError(error);
    } finally {
        this.loading = false;
    }
}
```

**Recommendation**: Create a store action wrapper utility.

```javascript
// src/utils/storeHelpers.js
export function createStoreAction(store, actionFn) {
    return async (...args) => {
        store.loading = true;
        store.error = null;
        try {
            return await actionFn(...args);
        } catch (error) {
            store.error = handleError(error);
            throw error; // Re-throw for caller handling
        } finally {
            store.loading = false;
        }
    };
}

// Usage in store
actions: {
    fetchPayrolls: createStoreAction(this, async (params) => {
        const response = await axiosInstance.get('/payrolls', { params });
        this.payrolls = response.data.data;
        this.meta = response.data.meta;
    })
}
```

**Trade-off**: Reduces boilerplate but adds indirection. Consider only for stores with 10+ similar actions.

---

### 3.4 localStorage Usage Without Error Handling

**Issue**: `useDarkMode.js` and `useSidebar.js` access `localStorage` without try-catch.

**Risk**: Safari private mode, storage quota exceeded, or disabled storage throws exceptions.

**Current**:
```javascript
// ❌ UNSAFE
localStorage.setItem("theme", isDark.value ? "dark" : "light");
const stored = localStorage.getItem("theme");
```

**Recommendation**:
```javascript
// ✅ SAFE
function safeLocalStorage() {
    try {
        const test = '__storage_test__';
        localStorage.setItem(test, test);
        localStorage.removeItem(test);
        return {
            getItem: (key) => localStorage.getItem(key),
            setItem: (key, value) => localStorage.setItem(key, value),
        };
    } catch {
        // Fallback to in-memory storage
        const store = new Map();
        return {
            getItem: (key) => store.get(key) ?? null,
            setItem: (key, value) => store.set(key, value),
        };
    }
}

const storage = safeLocalStorage();
storage.setItem("theme", isDark.value ? "dark" : "light");
```

---

### 3.5 Debounce in Views Instead of Stores

**Issue**: 30 instances of debounce/throttle, mostly in views. Search/filter debouncing should live in stores for reusability.

**Example** (`PayrollDetail.vue`):
```javascript
// ❌ CURRENT: Debounce in view
import { debounce } from "lodash";
const debouncedSearch = debounce((query) => {
    fetchPayrollDetails({ search: query });
}, 300);
```

**Recommendation**: Move to store action.

```javascript
// ✅ BETTER: Debounce in store
// src/stores/payroll.js
import { debounce } from "lodash";

actions: {
    searchPayrollDetails: debounce(async function(query) {
        this.loading = true;
        try {
            const response = await axiosInstance.get('/payrolls', { 
                params: { search: query } 
            });
            this.payrolls = response.data.data;
        } catch (error) {
            this.error = handleError(error);
        } finally {
            this.loading = false;
        }
    }, 300)
}
```

**Benefit**: Reusable across components, easier to test, centralized timing control.

---

### 3.6 Missing Abort Controllers for Concurrent Requests

**Issue**: Stores don't cancel in-flight requests when new ones are triggered (e.g., rapid filter changes).

**Risk**: Race conditions where older responses overwrite newer ones.

**Example Scenario**:
1. User types "John" → request A starts
2. User types "Jane" → request B starts
3. Request B completes → shows Jane results
4. Request A completes (slower) → overwrites with John results ❌

**Recommendation**: Use AbortController for cancellable requests.

```javascript
// src/stores/staffMember.js
state: () => ({
    abortController: null,
}),

actions: {
    async fetchStaffMembers(params) {
        // Cancel previous request
        if (this.abortController) {
            this.abortController.abort();
        }
        
        this.abortController = new AbortController();
        this.loading = true;
        
        try {
            const response = await axiosInstance.get('/staff-members', {
                params,
                signal: this.abortController.signal
            });
            this.staffMembers = response.data.data;
        } catch (error) {
            if (error.name === 'AbortError') return; // Ignore cancelled
            this.error = handleError(error);
        } finally {
            this.loading = false;
            this.abortController = null;
        }
    }
}
```

**Priority**: Medium (only affects rapid user interactions, not common in HRIS workflows).

---

## 4. POSITIVE PATTERNS (Keep Doing)

### 4.1 ✅ Strict Composition API Adherence
- **0 violations** of Options API usage
- All components use `<script setup>`
- Consistent reactive patterns (`ref`, `computed`, `watch`)

### 4.2 ✅ Clean Architecture Separation
- **Only 1 violation** of "API calls in stores" rule (easily fixed)
- Components focus on UI, stores handle business logic
- Clear data flow: Store → Component → User

### 4.3 ✅ Comprehensive Error Handling
- **281 try-catch blocks** in stores
- Centralized error helper (`handleError.js`)
- User-friendly error messages (no raw stack traces)

### 4.4 ✅ Memory Leak Prevention
- **20 cleanup hooks** (`onBeforeUnmount`, `onUnmounted`)
- Event listeners properly removed
- Intervals cleared (e.g., notification polling in `Header.vue`)

### 4.5 ✅ No XSS Vulnerabilities
- **0 instances** of `v-html`, `innerHTML`, `eval`, or `Function()`
- All user input rendered via `{{ }}` (auto-escaped)
- No dynamic script injection

### 4.6 ✅ Strong Test Coverage
- **969 tests passing** (127 test files)
- Unit tests for stores, components, composables
- Integration tests for router guards
- E2E tests for critical flows (95 Playwright tests)

### 4.7 ✅ Consistent Code Style
- 4-space indentation everywhere
- Prettier + ESLint enforced
- No console.log in production code

---

## 5. RECOMMENDATIONS SUMMARY

### Immediate (This Sprint)
1. **Update axios to 1.15.2+** (CRITICAL security fix)
2. **Move API call from `UpcomingCutiBersama.vue` to store** (architecture violation)
3. **Add security headers to Vite config** (dev environment)

### Short-Term (Next Sprint)
4. **Split large stores** (`payroll.js`, `analytics.js`, `performanceReview.js`)
5. **Extract components from large views** (`PayrollDetail.vue`, `ReviewDetail.vue`)
6. **Add localStorage error handling** (`useDarkMode.js`, `useSidebar.js`)

### Medium-Term (Next Quarter)
7. **Remove redundant getters** (4 stores)
8. **Fix index-as-key anti-pattern** (6 instances)
9. **Add AbortController for search/filter actions** (stores)
10. **Configure production security headers** (Nginx/Apache)

### Long-Term (Backlog)
11. **Create store action wrapper utility** (reduce boilerplate)
12. **Self-host Google Fonts** (tighten CSP to remove `unsafe-inline`)
13. **Add Trusted Types enforcement** (future-proof XSS defense)

---

## 6. METRICS

| Metric | Value | Status |
|--------|-------|--------|
| Total Files | 321 (Vue + JS) | ✅ |
| Views | 67 | ✅ |
| Components | 71 | ✅ |
| Stores | 25 | ✅ |
| Test Files | 127 | ✅ |
| Tests Passing | 969 | ✅ |
| Test Coverage | Not measured | ⚠️ |
| Largest Store | 745 lines | ⚠️ |
| Largest View | 2290 lines | ⚠️ |
| API Calls in Components | 1 | ⚠️ |
| XSS Vulnerabilities | 0 | ✅ |
| Dependency Vulnerabilities | 16 (axios) | ❌ |
| Options API Usage | 0 | ✅ |
| Event Listener Cleanup | 100% | ✅ |

---

## 7. CONCLUSION

The team-sync-fe codebase is **well-architected and maintainable**, with strong adherence to Vue 3 best practices and clean separation of concerns. The critical axios vulnerability requires immediate attention, but the overall code quality is high.

**Key Strengths**:
- Consistent architecture (Composition API, store-based API calls)
- Comprehensive test coverage (969 tests)
- No XSS vulnerabilities
- Proper memory management

**Key Weaknesses**:
- Critical dependency vulnerabilities (axios)
- Large files (stores and views) need splitting
- Missing security headers
- Minor architecture violations (1 component calling API directly)

**Next Steps**:
1. Update axios immediately
2. Schedule refactoring sprint for large files
3. Add security headers to deployment pipeline
4. Consider adding test coverage reporting

---

**Audit Completed**: 2026-05-14  
**Reviewed Files**: 321 source files, 25 stores, 67 views, 71 components  
**Tools Used**: grep, ast-grep, bun audit, manual code review  
**Follow-up**: Re-audit after axios update and large file refactoring
