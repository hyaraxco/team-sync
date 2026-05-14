# BE-FE Integration Patterns & Best Practices

> **Research Date**: 2026-05-14
> **Status**: Active Documentation
> **Scope**: Laravel 12 + Vue 3 HRIS System

---

## 1. API Contract Documentation

### 1.1 Current State

**No formal OpenAPI/Swagger documentation** — API contract is implicit through:
- **Postman Collection**: `team-sync-postman-collection.json` (6698 lines, 161.9KB)
- **JsonResource transformers**: 29 resource classes in `team-sync-be/app/Http/Resources/`
- **FormRequest validation**: Request classes in `team-sync-be/app/Http/Requests/`
- **E2E tests**: 101 test files covering API behavior

### 1.2 Response Structure

**Standardized via `ResponseHelper::jsonResponse()`**:

```php
// Backend (app/Helpers/ResponseHelper.php)
{
    "success": true|false,
    "message": "Human-readable message",
    "data": <JsonResource|array|null>
}
```

**Frontend consumption pattern**:
```javascript
// All stores follow this pattern
const response = await axiosInstance.get('/endpoint');
this.data = response.data.data;  // Unwrap data envelope
this.success = response.data.message;
```

### 1.3 Resource Transformation Examples

**PayrollResource** (lines 19-61):
- Includes `whenLoaded()` for relationships (lazy loading)
- Computes derived fields (`employee_count`, `total_amount`)
- Conditional fields based on status (`reconciliation_summary` only for pending/approved)
- Alias fields for compatibility (`period` → `salary_month`)

**UserResource** (lines 17-33):
- Eager-loads permissions via `getAllPermissions()->pluck('name')`
- Returns flat permission array for FE permission checks
- Conditional `token` field (only on login)

### 1.4 Gap: No Contract Tests

**Missing**: Dedicated contract tests verifying Resource output matches FE expectations.

**Recommendation**: Add Pest tests like:
```php
test('PayrollResource matches FE store expectations', function () {
    $payroll = Payroll::factory()->create();
    $resource = new PayrollResource($payroll);
    
    expect($resource->toArray(request()))
        ->toHaveKeys(['id', 'salary_month', 'status', 'employee_count']);
});
```

---

## 2. Error Handling Patterns

### 2.1 Backend Error Responses

**Validation Errors (422)**:
```php
// FormRequest auto-returns:
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "field_name": ["Error message 1", "Error message 2"]
    }
}
```

**Business Logic Errors (400/403/404/500)**:
```php
// Controllers use ResponseHelper:
return ResponseHelper::jsonResponse(false, $e->getMessage(), null, $status);
```

**No global exception handler** — Laravel's default handler is used.

### 2.2 Frontend Error Handling

**Centralized via `errorHelper.js`** (lines 1-25):

```javascript
export function handleError(error) {
    if (!error.response) {
        return "Network error. Please check your connection.";
    }
    
    const status = error.response.status;
    
    if (status === 422) {
        return error.response.data.errors;  // Object of field errors
    } else if (status === 401) {
        return error.response.data.message;
    } else if (status === 403) {
        return error.response.data.message || "You do not have permission...";
    } else if (status === 404) {
        return error.response.data.message || "Resource not found.";
    } else if (status === 429) {
        return "Too many requests. Please try again later.";
    } else if (status === 500) {
        return error.response.data.message;
    } else {
        return error.response.data?.message || "An unexpected error occurred.";
    }
}
```

**Store pattern**:
```javascript
try {
    const response = await axiosInstance.post('/endpoint', payload);
    this.success = response.data.message;
    return response.data.data;
} catch (error) {
    this.error = handleError(error);  // Sets store.error
    throw error;  // Re-throw for component handling
}
```

**Component pattern**:
```javascript
try {
    await store.someAction(payload);
    toast.success(store.success);
} catch (error) {
    // Store already set store.error
    toast.error(store.error);
}
```

### 2.3 Global 401 Interceptor

**Axios interceptor** (`plugins/axios.js` lines 22-33):
```javascript
axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            Cookies.remove("token");
            if (window.location.pathname !== "/auth/login") {
                window.location.href = "/auth/login";
            }
        }
        return Promise.reject(error);
    },
);
```

**Hard redirect** — no soft logout, immediate redirect to login.

### 2.4 Gap: No Structured Error Codes

**Missing**: Error codes for programmatic handling (e.g., `PAYROLL_ALREADY_GENERATED`, `LEAVE_QUOTA_EXCEEDED`).

**Current**: FE relies on string matching of error messages.

**Recommendation**: Add error codes to ResponseHelper:
```php
return ResponseHelper::jsonResponse(false, $message, null, $status, 'ERROR_CODE');
```

---

## 3. State Management Patterns

### 3.1 Store Architecture

**One store per domain** — 25 Pinia stores matching backend domains:
- `auth.js`, `payroll.js`, `attendance.js`, `leaveRequest.js`, `project.js`, etc.

**Store structure**:
```javascript
export const usePayrollStore = defineStore("payroll", {
    state: () => ({
        payrolls: [],           // List data
        meta: { ... },          // Pagination metadata
        loading: false,         // Loading state
        error: null,            // Error message
        success: null,          // Success message
    }),
    actions: {
        async fetchPayrolls(params) { ... },
        async generatePayroll(payload) { ... },
    },
});
```

### 3.2 Pessimistic Updates (Current Pattern)

**All stores use pessimistic updates** — no optimistic UI:

```javascript
async deleteProject(id) {
    this.loading = true;  // Show loading spinner
    try {
        await axiosInstance.delete(`/projects/${id}`);
        this.success = response.data.message;
        // Component refetches data after success
    } catch (error) {
        this.error = handleError(error);
    } finally {
        this.loading = false;
    }
}
```

**Component pattern**:
```javascript
async handleDelete(id) {
    await projectStore.deleteProject(id);
    if (!projectStore.error) {
        await projectStore.fetchProjects();  // Refetch list
    }
}
```

### 3.3 Cache Invalidation Strategy

**Manual refetch** — no automatic cache invalidation:
- After create/update/delete, component explicitly calls `fetch*()` again
- No cache keys, no TTL, no background refresh

**Example** (PayrollDetail.vue lines 1-100):
```javascript
const loadPayrollDetails = async () => {
    loading.value = true;
    try {
        payroll.value = await payrollStore.fetchPayroll(route.params.id);
        employees.value = payroll.value.payroll_details;
    } finally {
        loading.value = false;
    }
};

const handleApprove = async () => {
    await payrollStore.approvePayroll(payroll.value.id);
    await loadPayrollDetails();  // Manual refetch
};
```

### 3.4 Gap: No Optimistic Updates

**Missing**: Optimistic UI for fast-feeling interactions (e.g., mark notification as read, toggle favorite).

**Recommendation**: Add optimistic updates for idempotent actions:
```javascript
async markAsRead(notificationId) {
    // Optimistic update
    const notification = this.notifications.find(n => n.id === notificationId);
    const originalState = notification.read_at;
    notification.read_at = new Date().toISOString();
    
    try {
        await axiosInstance.post(`/my-notifications/${notificationId}/mark-as-read`);
    } catch (error) {
        // Rollback on error
        notification.read_at = originalState;
        this.error = handleError(error);
    }
}
```

---

## 4. Authentication Flow

### 4.1 Sanctum SPA Authentication

**Token-based** (not cookie-based SPA auth):
- Login returns `token` in response
- FE stores token in cookie (`js-cookie`)
- Axios interceptor adds `Authorization: Bearer {token}` header

**Login flow** (`auth.js` lines 19-44):
```javascript
async login(credentials) {
    const response = await axiosInstance.post("/login", authPayload);
    const token = response.data.data.token;
    
    if (remember) {
        Cookies.set("token", token, { expires: 30 });  // 30 days
    } else {
        Cookies.set("token", token);  // Session cookie
    }
    
    router.push({ name: "admin.dashboard" });
}
```

**Auth check** (`auth.js` lines 46-61):
```javascript
async checkAuth() {
    const response = await axiosInstance.get("/me");
    this.user = response.data.data;  // Includes roles, permissions
    return this.user;
}
```

**Router guard** (`router/index.js`):
```javascript
router.beforeEach(async (to, from, next) => {
    if (to.meta.requiresAuth) {
        if (!authStore.token) {
            return next({ name: "login" });
        }
        
        if (!authStore.user) {
            await authStore.checkAuth();
        }
        
        // Permission check
        if (!hasRoutePermissionAccess(authStore.user.permissions, to.meta)) {
            return next({ name: "admin.dashboard" });
        }
    }
    next();
});
```

### 4.2 CSRF Handling

**NOT using CSRF tokens** — Sanctum SPA auth typically uses CSRF, but this project uses Bearer tokens instead.

**Sanctum config** (`config/sanctum.php` lines 21-26):
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort(),
))),
```

**Gap**: Sanctum is configured for stateful domains, but FE uses Bearer tokens. This is a **hybrid approach** — not fully SPA-style (cookie-based) nor fully API-style (stateless tokens).

**Recommendation**: Choose one:
1. **Full SPA**: Remove token from response, use `sanctum/csrf-cookie` endpoint, rely on cookies
2. **Full API**: Remove stateful domains, use only Bearer tokens

### 4.3 Session Timeout Handling

**No explicit session timeout** — relies on 401 interceptor.

**Logout flow** (`auth.js` lines 63-107):
```javascript
async logout() {
    const token = this.token;
    Cookies.remove("token");
    delete axiosInstance.defaults.headers.common.Authorization;
    
    // Hard redirect (fallback for stuck SPA)
    window.location.replace("/auth/login");
    
    this.user = null;
    
    // Best-effort server-side revoke (background)
    void axiosInstance.post("/logout", null, {
        timeout: 5000,
        headers: { Authorization: `Bearer ${token}` },
    }).catch(() => {});
}
```

**Hard redirect** — ensures logout even if SPA navigation fails.

### 4.4 Gap: No Token Refresh

**Missing**: Token refresh mechanism. Tokens don't expire (no `expires_at` in UserResource).

**Recommendation**: Add token expiration + refresh endpoint:
```php
// Backend
'expiration' => 60 * 24,  // 24 hours (config/sanctum.php)

// Frontend
axiosInstance.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401 && error.config && !error.config.__isRetry) {
            error.config.__isRetry = true;
            await authStore.refreshToken();
            return axiosInstance(error.config);
        }
        return Promise.reject(error);
    }
);
```

---

## 5. Real-time Updates

### 5.1 Current State: No Real-time

**No polling, no websockets, no SSE** — all data fetched on-demand.

**Notification delivery**:
- Notifications queued via database driver
- FE fetches via `/api/v1/my-notifications` (manual refresh)
- No push notifications, no live updates

**Queue worker status**:
- FE has no visibility into queue worker status
- If queue worker is down, notifications silently fail to deliver
- No health check endpoint

### 5.2 Gap: Queue Worker Health Check

**Missing**: Endpoint to check if queue worker is running.

**Recommendation**: Add health check:
```php
// Backend
Route::get('health/queue', function () {
    $lastJob = DB::table('jobs')->latest('id')->first();
    $isHealthy = $lastJob ? (time() - $lastJob->available_at < 300) : true;
    
    return response()->json(['healthy' => $isHealthy]);
});

// Frontend (in notification store)
async checkQueueHealth() {
    const response = await axiosInstance.get('/health/queue');
    return response.data.healthy;
}
```

### 5.3 Gap: No Live Notifications

**Missing**: Real-time notification delivery (e.g., "New leave request needs approval").

**Recommendation**: Add polling or websockets:

**Option 1: Polling** (simpler, no infrastructure change):
```javascript
// In notification store
startPolling() {
    this.pollingInterval = setInterval(async () => {
        const count = await this.fetchUnreadCount();
        if (count > this.lastUnreadCount) {
            // Show toast notification
            toast.info(`You have ${count - this.lastUnreadCount} new notifications`);
        }
        this.lastUnreadCount = count;
    }, 30000);  // Poll every 30s
}
```

**Option 2: Laravel Echo + Pusher** (real-time):
```javascript
// Install: bun add laravel-echo pusher-js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authorizer: (channel) => ({
        authorize: (socketId, callback) => {
            axiosInstance.post('/broadcasting/auth', {
                socket_id: socketId,
                channel_name: channel.name,
            }).then(response => {
                callback(false, response.data);
            }).catch(error => {
                callback(true, error);
            });
        },
    }),
});

// Listen to private channel
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        notificationStore.addNotification(notification);
        toast.info(notification.title);
    });
```

---

## 6. File Upload/Download Flows

### 6.1 Upload Pattern

**FormData + multipart/form-data**:

**Leave proof upload** (`leaveRequest.js` lines 181-199):
```javascript
async uploadProof(id, file) {
    const formData = new FormData();
    formData.append("proof_file", file);
    
    const response = await axiosInstance.post(
        `leave-requests/${id}/proof`,
        formData,
        {
            headers: { "Content-Type": "multipart/form-data" },
        }
    );
    
    return response.data.data;
}
```

**Profile photo upload** (`auth.js` lines 109-143):
```javascript
async updateProfile(payload) {
    const formData = new FormData();
    
    if (payload?.name) formData.append("name", payload.name);
    if (payload?.password) {
        formData.append("password", payload.password);
        formData.append("password_confirmation", payload.password_confirmation);
    }
    if (payload?.profile_photo instanceof File) {
        formData.append("profile_photo", payload.profile_photo);
    }
    
    formData.append("_method", "PUT");  // Laravel method spoofing
    
    const response = await axiosInstance.post("/me", formData);
    await this.checkAuth();  // Refresh user data
}
```

**Task attachment upload** (`task.js`):
```javascript
const formData = new FormData();
formData.append("file", file);
formData.append("uploaded_by", userId);

await axiosInstance.post(`/project-tasks/${taskId}/attachments`, formData, {
    headers: { "Content-Type": "multipart/form-data" },
});
```

### 6.2 Download Pattern

**Blob download with Content-Disposition parsing**:

**Payslip PDF download** (`payroll.js` lines 5-31):
```javascript
const triggerBlobDownload = (response, fallbackFilename) => {
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    
    // Parse filename from Content-Disposition header
    const contentDisposition = response.headers["content-disposition"];
    let filename = fallbackFilename;
    if (contentDisposition) {
        const utf8FilenameMatch = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
        const quotedFilenameMatch = contentDisposition.match(/filename="([^"]+)"/i);
        const plainFilenameMatch = contentDisposition.match(/filename=([^;]+)/i);
        
        if (utf8FilenameMatch) {
            filename = decodeURIComponent(utf8FilenameMatch[1]);
        } else if (quotedFilenameMatch) {
            filename = quotedFilenameMatch[1];
        } else if (plainFilenameMatch) {
            filename = plainFilenameMatch[1].trim();
        }
    }
    
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
};
```

**Excel export** (`payroll.js`):
```javascript
async exportPayrollReport(params) {
    const response = await axiosInstance.get("/payrolls/export", {
        params,
        responseType: "blob",  // Important for binary data
    });
    
    const fallbackFilename = buildPayrollReportFallbackFilename(params);
    triggerBlobDownload(response, fallbackFilename);
}
```

**Backend** (uses `barryvdh/laravel-dompdf` for PDF, `maatwebsite/excel` for Excel):
```php
// PayslipController
public function download($id) {
    $payrollDetail = PayrollDetail::findOrFail($id);
    $pdf = PDF::loadView('payslips.template', compact('payrollDetail'));
    
    return $pdf->download("payslip_{$payrollDetail->id}.pdf");
}

// PayrollController
public function export(Request $request) {
    return Excel::download(
        new PayrollExport($request->validated()),
        'payroll_report.xlsx'
    );
}
```

### 6.3 Gap: No Upload Progress

**Missing**: Upload progress indicator for large files.

**Recommendation**: Add progress tracking:
```javascript
async uploadProof(id, file, onProgress) {
    const formData = new FormData();
    formData.append("proof_file", file);
    
    const response = await axiosInstance.post(
        `leave-requests/${id}/proof`,
        formData,
        {
            headers: { "Content-Type": "multipart/form-data" },
            onUploadProgress: (progressEvent) => {
                const percentCompleted = Math.round(
                    (progressEvent.loaded * 100) / progressEvent.total
                );
                onProgress?.(percentCompleted);
            },
        }
    );
}
```

---

## 7. Permission Synchronization

### 7.1 Backend: Spatie Permissions

**Role-based access control** via `spatie/laravel-permission`:

**Permission middleware** (routes/api.php lines 78-79):
```php
Route::post('meetings', [MeetingController::class, 'store'])
    ->middleware(PermissionMiddleware::using('meeting-create'));
```

**User permissions** loaded via `UserResource` (lines 28-30):
```php
'permissions' => $this->whenLoaded('permissions', function () {
    return $this->getAllPermissions()->pluck('name');
}),
```

**Backend returns flat array of permission names**:
```json
{
    "permissions": [
        "dashboard-menu",
        "staff-member-list",
        "payroll-create",
        "payroll-process"
    ]
}
```

### 7.2 Frontend: Permission Checks

**Permission helper** (`helpers/permissionHelper.js`):
```javascript
import { useAuthStore } from "@/stores/auth";

export function can(permission) {
    const authStore = useAuthStore();
    return authStore.user?.permissions?.includes(permission) ?? false;
}

export function canOneOf(permissions) {
    const authStore = useAuthStore();
    return permissions.some(p => authStore.user?.permissions?.includes(p));
}
```

**Route guards** (`router/permissionAccess.js` lines 4-31):
```javascript
export const hasRoutePermissionAccess = (permissions = [], meta = {}) => {
    const normalizedPermissions = normalizePermissions(permissions);
    const requiredPermission = meta.requiredPermission;
    const requiredAnyPermissions = meta.requiredAnyPermissions;
    
    // Fail closed for authenticated pages
    if (meta.requiresAuth && !hasExplicitPermissionGuard && !meta.allowAuthenticated) {
        return false;
    }
    
    if (requiredPermission && !normalizedPermissions.includes(requiredPermission)) {
        return false;
    }
    
    if (requiredAnyPermissions?.length > 0 &&
        !requiredAnyPermissions.some(p => normalizedPermissions.includes(p))) {
        return false;
    }
    
    return true;
};
```

**Component usage**:
```vue
<script setup>
import { can } from "@/helpers/permissionHelper";

const canCreatePayroll = computed(() => can("payroll-create"));
const canProcessPayroll = computed(() => can("payroll-process"));
</script>

<template>
    <button v-if="canCreatePayroll" @click="generatePayroll">
        Generate Payroll
    </button>
</template>
```

### 7.3 Permission Alignment

**BE middleware vs FE route guards**:

| Route | BE Middleware | FE Route Meta |
|-------|---------------|---------------|
| `/payrolls/generate` | `payroll-create` | `payroll-create` |
| `/meetings` (POST) | `meeting-create` | `meeting-create` |
| `/staff-members` | (none — open to auth) | `staff-member-list` |

**Alignment is manual** — no automated sync between BE middleware and FE route meta.

### 7.4 Gap: Permission Sync Validation

**Missing**: Test to verify BE middleware matches FE route guards.

**Recommendation**: Add E2E test:
```javascript
// e2e/permission-alignment.spec.js
test('FE route guards match BE middleware', async ({ page }) => {
    const routes = await page.evaluate(() => {
        return window.$router.getRoutes()
            .filter(r => r.meta.requiredPermission)
            .map(r => ({
                path: r.path,
                permission: r.meta.requiredPermission,
            }));
    });
    
    for (const route of routes) {
        // Call BE endpoint without permission
        const response = await fetch(`/api/v1${route.path}`, {
            headers: { Authorization: `Bearer ${tokenWithoutPermission}` },
        });
        
        expect(response.status).toBe(403);  // Should be forbidden
    }
});
```

---

## 8. Best Practice Recommendations

### 8.1 API Contract

**Priority: HIGH**

1. **Add OpenAPI/Swagger documentation**:
   - Install `darkaonline/l5-swagger`
   - Annotate controllers with `@OA\` tags
   - Generate `/api/documentation` endpoint

2. **Add contract tests**:
   - Verify Resource output matches FE expectations
   - Test validation rules match FE form validation

3. **Add error codes**:
   - Structured error codes for programmatic handling
   - Document error codes in OpenAPI spec

### 8.2 Error Handling

**Priority: MEDIUM**

1. **Add global exception handler**:
   - Catch all exceptions, return consistent format
   - Log 500 errors with context

2. **Add error boundary in FE**:
   - Catch unhandled errors in components
   - Show user-friendly error page

3. **Add retry logic**:
   - Retry failed requests (with exponential backoff)
   - Show "Retry" button on network errors

### 8.3 State Management

**Priority: MEDIUM**

1. **Add optimistic updates**:
   - For idempotent actions (mark as read, toggle favorite)
   - Rollback on error

2. **Add cache invalidation strategy**:
   - Use cache keys + TTL
   - Invalidate related caches on mutation

3. **Add background refresh**:
   - Refresh stale data in background
   - Show "New data available" toast

### 8.4 Authentication

**Priority: HIGH**

1. **Choose auth strategy**:
   - Either full SPA (cookie-based) or full API (Bearer tokens)
   - Remove hybrid approach

2. **Add token refresh**:
   - Set token expiration (24h)
   - Add refresh endpoint
   - Auto-refresh before expiration

3. **Add CSRF protection** (if using SPA auth):
   - Call `/sanctum/csrf-cookie` before login
   - Remove Bearer token approach

### 8.5 Real-time Updates

**Priority: LOW**

1. **Add queue health check**:
   - Endpoint to check queue worker status
   - Show warning if queue is down

2. **Add notification polling**:
   - Poll `/my-notifications/unread-count` every 30s
   - Show toast on new notifications

3. **Consider Laravel Echo** (future):
   - Real-time notifications via websockets
   - Requires Pusher/Redis setup

### 8.6 File Uploads

**Priority: LOW**

1. **Add upload progress**:
   - Show progress bar for large files
   - Use `onUploadProgress` callback

2. **Add file validation**:
   - Client-side validation (size, type)
   - Show preview before upload

3. **Add chunked uploads** (future):
   - For files >10MB
   - Resume failed uploads

### 8.7 Permissions

**Priority: MEDIUM**

1. **Add permission sync test**:
   - E2E test verifying BE middleware matches FE guards
   - Run on every deployment

2. **Add permission caching**:
   - Cache user permissions in localStorage
   - Refresh on login/logout

3. **Add permission audit log**:
   - Log permission checks (who accessed what)
   - For compliance and debugging

---

## 9. Existing Documentation References

### 9.1 Internal Docs

- **Root AGENTS.md**: Architecture overview, domain rules, role hierarchy
- **BE AGENTS.md** (`team-sync-be/AGENTS.md`): Layering, models, services, commands
- **FE AGENTS.md** (`team-sync-fe/AGENTS.md`): Components, stores, routing, testing
- **PRD** (`docs/references/prd.md`): Feature breakdown, user flows
- **Payroll Reference** (`docs/references/payroll.md`): Payroll domain rules
- **Attendance Reference** (`docs/references/attendance.md`): Attendance policies
- **BE-FE Gap Audit** (`docs/plans/archive/plan-audit-be-fe-gap.md`): Completed gap analysis

### 9.2 External References

- **Laravel Sanctum**: https://laravel.com/docs/12.x/sanctum
- **Pinia**: https://pinia.vuejs.org/
- **Vue Router**: https://router.vuejs.org/
- **Spatie Permissions**: https://spatie.be/docs/laravel-permission/v6/introduction

---

## 10. Summary

### 10.1 Current Strengths

✅ **Consistent response format** via ResponseHelper
✅ **Centralized error handling** via errorHelper
✅ **Permission-based routing** with fail-closed defaults
✅ **Comprehensive E2E tests** (95 Playwright tests)
✅ **Postman collection** for manual API testing
✅ **Standardized file upload/download** patterns

### 10.2 Critical Gaps

❌ **No OpenAPI/Swagger documentation**
❌ **No contract tests** (Resource ↔ Store alignment)
❌ **No token refresh** mechanism
❌ **No queue health check** (FE blind to queue worker status)
❌ **No real-time notifications** (manual refresh only)
❌ **No permission sync validation** (BE middleware vs FE guards)

### 10.3 Next Steps

1. **Add OpenAPI documentation** (HIGH priority)
2. **Add token refresh** (HIGH priority)
3. **Add contract tests** (MEDIUM priority)
4. **Add queue health check** (MEDIUM priority)
5. **Add notification polling** (LOW priority)
6. **Add permission sync test** (MEDIUM priority)

---

**End of Document**
