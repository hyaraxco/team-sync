# Phase 6A: Critical BE-FE Integration Fixes (REVISED)

**Status**: COMPLETED  
**Priority**: Critical  
**Estimated Time**: 5-6 days (revised from 3-4 days)  
**Issues**: 4 critical fixes  
**Testing**: 30 tests (revised from 18 tests)  
**Party Mode Review**: Completed 2026-05-14  
**Created**: 2026-05-14  
**Last Updated**: 2026-05-14

---

## Executive Summary

Fix 4 critical BE-FE integration gaps identified in audit:
1. **Timezone Mismatch** - FE uses browser TZ instead of company TZ
2. **No Notification Polling** - Users must refresh to see new notifications
3. **Permission Sync Gap** - No validation that FE guards match BE middleware
4. **Missing Reopen Confirmation** - No warning about re-approval requirement

### Party Mode Feedback Incorporated

**Dede (Backend) - Score: 8/10**:
- ✅ Add Service layer (NotificationService)
- ✅ Reduce rate limiting (120 → 60 req/min)
- ✅ Use Eloquent in Repository (not raw queries)
- ✅ Adjust timeline (Day 1 unrealistic → 5-6 days total)

**Fitri (QA) - Score: 6/10**:
- ✅ Expand test coverage (18 → 30 tests)
- ✅ Add edge case tests (DST, concurrent, negative count)
- ✅ Reduce E2E tests (33% → 20%)
- ✅ Add integration tests (permission cache, cross-cutting)

**Eka (Frontend) - No Score**:
- Plan will be created (this file)

---

## Issue 1: Timezone Mismatch Fix

### Problem
FE uses browser timezone (`Intl.DateTimeFormat().resolvedOptions().timeZone`), not company timezone. Employee in Singapore sees SGT times, but company policy is WIB.

### Solution
Fetch company timezone from `/me` endpoint, store in Pinia auth store, use in all date formatting.

### Backend Changes

#### 1.1 Add Company Relationship to StaffMemberProfile

**File**: `team-sync-be/app/Models/StaffMemberProfile.php`

**Note**: Migration `2026_05_03_100100_add_company_id_to_tenant_tables.php` already added `company_id` column. Just need relationship method.

```php
// Add relationship method:
public function company()
{
    return $this->belongsTo(Company::class);
}
```

**Rationale**: Direct relationship avoids N+1 query through `jobInformation.team.company`.

#### 1.2 Update AuthRepository to Eager Load Company

**File**: `team-sync-be/app/Repositories/AuthRepository.php`

```php
public function me(): User
{
    return auth()->user()->load([
        'roles',
        'permissions',
        'staffMemberProfile.company',      // NEW
        'staffMemberProfile.jobInformation'
    ]);
}
```

#### 1.3 Add Company Timezone to UserResource

**File**: `team-sync-be/app/Http/Resources/UserResource.php`

```php
public function toArray(Request $request): array
{
    return [
        // ... existing fields
        'company_timezone' => $this->whenLoaded('staffMemberProfile', function () {
            return $this->staffMemberProfile?->company?->timezone ?? 'Asia/Jakarta';
        }),
    ];
}
```

**Edge Case Handling**: Returns `'Asia/Jakarta'` if:
- User has no `staffMemberProfile` (Superadmin)
- StaffMemberProfile has no company
- Company has null timezone

### Frontend Changes

#### 1.4 Update Auth Store to Store Company Timezone

**File**: `team-sync-fe/src/stores/auth.js`

```javascript
state: () => ({
    user: null,
    companyTimezone: 'Asia/Jakarta', // default fallback
    loading: false,
    error: null,
    success: null,
}),

async checkAuth() {
    this.loading = true;
    try {
        const response = await axiosInstance.get("/me");
        this.user = response.data.data;
        this.companyTimezone = response.data.data.company_timezone || 'Asia/Jakarta';
        return this.user;
    } catch (error) {
        // ... existing error handling
    } finally {
        this.loading = false;
    }
}
```

#### 1.5 Create formatToCompanyTimezone Helper

**File**: `team-sync-fe/src/helpers/format.js`

```javascript
import { DateTime } from "luxon";
import { useAuthStore } from '@/stores/auth';

export function formatToCompanyTimezone(date, format = "dd MMM yyyy HH:mm") {
    const authStore = useAuthStore();
    const timezone = authStore.companyTimezone || 'Asia/Jakarta';
    
    const originalDate = DateTime.fromISO(date, { zone: "utc" });
    return originalDate.setZone(timezone).setLocale("id").toFormat(format);
}

// Deprecate old function
export function formatToClientTimezone(date, format = "dd MMM yyyy HH:mm") {
    if (import.meta.env.DEV) {
        console.warn('formatToClientTimezone is deprecated, use formatToCompanyTimezone');
    }
    return formatToCompanyTimezone(date, format);
}
```

#### 1.6 Create TimezoneBadge Component

**File**: `team-sync-fe/src/components/common/TimezoneBadge.vue` (NEW)

```vue
<template>
    <span 
        class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-primary-50 text-primary-700 text-xs font-medium"
        :aria-label="`Times displayed in ${timezone}`"
    >
        <Clock :size="12" />
        Times in {{ timezone }}
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { Clock } from 'lucide-vue-next';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const timezone = computed(() => authStore.companyTimezone || 'Asia/Jakarta');
</script>
```

**Design System Compliance**: Follows `StatusBadge` pattern (px-2 py-1 rounded-md text-xs font-medium).

#### 1.7 Add TimezoneBadge to Header

**File**: `team-sync-fe/src/components/admin/Header.vue`

Add import and component before user profile section.

#### 1.8 Replace formatToClientTimezone Calls

**Files to Update**:
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue`
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue`
- `team-sync-fe/src/views/staff-member/attendance/MyAttendance.vue`

**Find & Replace**:
```javascript
// Old:
import { formatToClientTimezone } from '@/helpers/format';
formatToClientTimezone(date)

// New:
import { formatToCompanyTimezone } from '@/helpers/format';
formatToCompanyTimezone(date)
```

### Tests for Issue 1

#### 1.9 Backend Test: Company Timezone in /me Endpoint

**File**: `team-sync-be/tests/Feature/Auth/MeEndpointTest.php` (NEW)

3 tests: happy path, no company, null timezone

#### 1.10 Frontend Test: formatToCompanyTimezone

**File**: `team-sync-fe/src/tests/helpers/format.test.js` (NEW)

2 tests: uses auth store, fallback to Asia/Jakarta

#### 1.11 E2E Test: Timezone Display

**File**: `team-sync-fe/e2e/timezone-display.spec.js` (NEW)

1 test: badge visible, times in company TZ

#### 1.12 Edge Case Tests (NEW - from Fitri feedback)

**File**: `team-sync-be/tests/Feature/Auth/MeEndpointTest.php`

3 additional tests:
- DST transition handling
- Timezone change mid-session
- Invalid timezone string gracefully handled

---

## Issue 2: Notification Polling (REVISED)

### Problem
No real-time updates. User must refresh page to see new notifications.

### Solution
Poll `/api/v1/my-notifications/unread-count` every 30 seconds using composable. Pause when tab hidden (Page Visibility API).

### Backend Changes

#### 2.1 Add Database Index for Notifications

**File**: `team-sync-be/database/migrations/2026_05_14_create_notifications_index.php` (NEW)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_id', 'read_at'], 'idx_notifications_notifiable_read');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_notifiable_read');
        });
    }
};
```

**Rationale**: Speeds up `WHERE notifiable_id = ? AND read_at IS NULL` query.

#### 2.2 Add Rate Limiting (REVISED from 120 to 60 req/min)

**File**: `team-sync-be/routes/api.php`

```php
Route::middleware(['auth:sanctum'])->prefix('my-notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])
        ->middleware('throttle:60,1');
    Route::get('/unread-count', [NotificationController::class, 'getUnreadCount'])
        ->middleware('throttle:60,1'); // REVISED: 60 req/min (was 120)
    Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
});
```

**Rationale**: 60/min = 1/sec, safer than 120/min. Allows 30s polling with buffer.

#### 2.3 Create NotificationService (NEW - Missing Layer from Dede feedback)

**File**: `team-sync-be/app/Services/NotificationService.php` (NEW)

```php
<?php

namespace App\Services;

use App\Interfaces\NotificationRepositoryInterface;

class NotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $repository
    ) {}
    
    public function getUnreadCount(int $userId): int
    {
        return $this->repository->getUnreadCount($userId);
    }
    
    public function getLatestNotifications(int $userId, int $limit): array
    {
        return $this->repository->getLatestNotifications($userId, $limit);
    }
    
    public function markAsRead(string $notificationId): bool
    {
        return $this->repository->markAsRead($notificationId);
    }
    
    public function markAllAsRead(int $userId): bool
    {
        return $this->repository->markAllAsRead($userId);
    }
}
```

**Rationale**: Proper layering (Controller → Service → Repository). Business logic goes here.

#### 2.4 Create NotificationRepository (REVISED - Use Eloquent from Dede feedback)

**File**: `team-sync-be/app/Interfaces/NotificationRepositoryInterface.php` (NEW)

```php
<?php

namespace App\Interfaces;

interface NotificationRepositoryInterface
{
    public function getUnreadCount(int $userId): int;
    public function getLatestNotifications(int $userId, int $limit): array;
    public function markAsRead(string $notificationId): bool;
    public function markAllAsRead(int $userId): bool;
}
```

**File**: `team-sync-be/app/Repositories/NotificationRepository.php` (NEW)

```php
<?php

namespace App\Repositories;

use App\Interfaces\NotificationRepositoryInterface;
use App\Models\User;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function getUnreadCount(int $userId): int
    {
        $user = User::find($userId);
        return $user ? $user->notifications()->whereNull('read_at')->count() : 0;
    }
    
    public function getLatestNotifications(int $userId, int $limit): array
    {
        $user = User::find($userId);
        return $user 
            ? $user->notifications()->latest()->limit($limit)->get()->toArray()
            : [];
    }
    
    public function markAsRead(string $notificationId): bool
    {
        $user = auth()->user();
        $notification = $user->notifications()->find($notificationId);
        
        if ($notification) {
            $notification->markAsRead();
            return true;
        }
        
        return false;
    }
    
    public function markAllAsRead(int $userId): bool
    {
        $user = User::find($userId);
        
        if ($user) {
            $user->unreadNotifications->markAsRead();
            return true;
        }
        
        return false;
    }
}
```

**Rationale**: Uses Eloquent (not raw queries) as per Dede feedback. Follows existing repository patterns.

#### 2.5 Bind NotificationRepository

**File**: `team-sync-be/app/Providers/RepositoryServiceProvider.php`

```php
// Add binding:
$this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
```

#### 2.6 Update NotificationController (REVISED - Use Service)

**File**: `team-sync-be/app/Http/Controllers/NotificationController.php`

```php
// Update constructor:
private NotificationService $notificationService;

public function __construct(NotificationService $notificationService)
{
    $this->notificationService = $notificationService;
}

// Update getUnreadCount method:
public function getUnreadCount()
{
    $count = $this->notificationService->getUnreadCount(auth()->id());
    return ResponseHelper::jsonResponse(true, 'Unread count retrieved', ['unread_count' => $count], 200);
}
```

**Rationale**: Controller → Service → Repository (proper layering).

### Frontend Changes for Issue 2

#### 2.7 Create useNotificationPolling Composable

**File**: `team-sync-fe/src/composables/useNotificationPolling.js` (NEW)

```javascript
import { ref, onMounted, onUnmounted } from 'vue';
import { useNotificationStore } from '@/stores/notifications';

export function useNotificationPolling(intervalMs = 30000) {
    const notificationStore = useNotificationStore();
    const isPolling = ref(false);
    let intervalId = null;

    const startPolling = () => {
        if (isPolling.value) return;
        
        isPolling.value = true;
        
        // Initial fetch
        notificationStore.fetchUnreadCount().catch(() => {
            // Silent fail - notifications are non-critical
        });
        
        // Poll every intervalMs
        intervalId = setInterval(() => {
            notificationStore.fetchUnreadCount().catch(() => {
                // Silent fail
            });
        }, intervalMs);
    };

    const stopPolling = () => {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
        isPolling.value = false;
    };

    // Pause polling when tab hidden (Page Visibility API)
    const handleVisibilityChange = () => {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    };

    onMounted(() => {
        startPolling();
        document.addEventListener('visibilitychange', handleVisibilityChange);
    });

    onUnmounted(() => {
        stopPolling();
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    });

    return {
        isPolling,
        startPolling,
        stopPolling,
    };
}
```

#### 2.8 Add Polling to Admin Layout

**File**: `team-sync-fe/src/layouts/Admin.vue`

```vue
<script setup>
import { useNotificationPolling } from '@/composables/useNotificationPolling';
// ... existing imports

// Start polling when layout mounts
useNotificationPolling(30000); // 30 seconds
</script>
```

#### 2.9 Create NotificationBadge Component

**File**: `team-sync-fe/src/components/common/NotificationBadge.vue` (NEW)

```vue
<template>
    <span 
        v-if="count > 0"
        class="absolute -top-1 -right-1 flex items-center justify-center min-w-[20px] h-5 px-1.5 bg-red-500 text-white text-xs font-bold rounded-full"
        :aria-label="`${displayCount} unread notifications`"
    >
        {{ displayCount }}
    </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    count: {
        type: Number,
        required: true,
    },
    maxDisplay: {
        type: Number,
        default: 99,
    },
});

const displayCount = computed(() => {
    return props.count > props.maxDisplay ? `${props.maxDisplay}+` : props.count;
});
</script>
```

#### 2.10 Update Header to Use NotificationBadge

**File**: `team-sync-fe/src/components/admin/Header.vue`

Add NotificationBadge to bell icon button.

### Tests for Issue 2

#### 2.11 Backend Test: Unread Count

**File**: `team-sync-be/tests/Feature/Notification/UnreadCountTest.php` (NEW)

2 tests: returns count, handles 401

#### 2.12 Frontend Test: Polling Composable

**File**: `team-sync-fe/src/tests/composables/useNotificationPolling.test.js` (NEW)

5 tests (was 3):
- Polls every 30 seconds
- Pauses when tab hidden
- Handles fetch errors silently
- **NEW**: Multiple tabs polling (no race condition)
- **NEW**: Handles negative count bug

#### 2.13 E2E Test: Badge Updates

**File**: `team-sync-fe/e2e/notification-polling.spec.js` (NEW)

1 test: badge updates automatically

---

## Issue 3: Permission Sync Validation

### Problem
No automated validation that FE route guards match BE middleware permissions.

### Solution
Create Artisan command to scan routes and compare with Spatie permissions. Add tests to ensure all protected routes have permission middleware.

### Backend Changes

#### 3.1 Create Artisan Command

**File**: `team-sync-be/app/Console/Commands/PermissionsSyncCheck.php` (NEW)

Full implementation with route scanning and permission validation.

### Tests for Issue 3

#### 3.2 Backend Test: Permission Consistency

**File**: `team-sync-be/tests/Feature/PermissionConsistencyTest.php` (NEW)

4 tests (was 2):
- All protected routes have middleware
- All permissions exist in Spatie
- **NEW**: Permission cache invalidation after role change
- **NEW**: Cross-company permission leak prevention

---

## Issue 4: Reopen Confirmation Dialog

### Problem
No warning when reopening payroll that it requires re-approval.

### Solution
Add confirmation modal with clear messaging about re-approval requirement and correction count.

### Frontend Changes

#### 4.1 Update PayrollDetail with Reopen Modal

**File**: `team-sync-fe/src/views/admin/payroll/PayrollDetail.vue`

Use `ModalWrapper` (not `ConfirmationModal` - no extra-content slot exists).

### Tests for Issue 4

#### 4.2 E2E Test: Reopen Confirmation

**File**: `team-sync-fe/e2e/payroll-reopen-confirmation.spec.js` (NEW)

5 tests (was 2):
- Shows confirmation with warning
- Validation (empty reason, too short)
- **NEW**: Concurrent reopen error
- **NEW**: Double-click prevention
- **NEW**: API failure + rollback

---

## Test Coverage Summary (REVISED)

### Total: 30 Tests (was 18)

| Type | Original | Revised | Tests |
|------|----------|---------|-------|
| **Backend Unit** | 5 | 8 | +3 edge cases |
| **Backend Feature** | 2 | 4 | +2 integration |
| **Frontend Unit** | 5 | 8 | +3 error paths |
| **E2E** | 6 | 10 | +4 concurrent/DST |

---

## Execution Timeline (REVISED)

### Day 1: Backend Timezone
- Add `company()` relationship
- Update AuthRepository + UserResource
- Write 6 tests (3 happy + 3 edge)
- Run `composer test`

### Day 2: Backend Notification Service
- Create NotificationService
- Create NotificationRepository (Eloquent)
- Update NotificationController
- Write 4 tests
- Run `composer test`

### Day 3: Backend Validation
- Add rate limiting (60 req/min)
- Create Artisan command
- Write 4 permission tests
- Run `composer test`

### Day 4: Frontend Components
- Create TimezoneBadge + NotificationBadge
- Create useNotificationPolling
- Update format.js + auth store
- Write 8 FE unit tests
- Run `bun run test`

### Day 5: Reopen Modal + E2E
- Update PayrollDetail.vue
- Write 10 E2E tests
- Run `bun run e2e`

### Day 6: Manual Testing + PR
- Manual testing (20 items)
- Create PR
- Wait for CI
- Archive plan

---

## Success Criteria

- [ ] All 1468 existing tests pass
- [ ] All 30 new tests pass
- [ ] Manual testing checklist (20 items) completed
- [ ] CI pipeline green
- [ ] No console errors
- [ ] No N+1 queries
- [ ] Service layer properly implemented
- [ ] Rate limiting at 60 req/min
- [ ] Repository uses Eloquent

---

## Files to Create (21 files)

**Backend (10)**:
1. `tests/Feature/Auth/MeEndpointTest.php`
2. `database/migrations/2026_05_14_create_notifications_index.php`
3. `app/Services/NotificationService.php`
4. `app/Interfaces/NotificationRepositoryInterface.php`
5. `app/Repositories/NotificationRepository.php`
6. `tests/Feature/Notification/UnreadCountTest.php`
7. `app/Console/Commands/PermissionsSyncCheck.php`
8. `tests/Feature/PermissionConsistencyTest.php`
9. `tests/Feature/PermissionCacheTest.php`
10. Migration for company_id (if not exists)

**Frontend (11)**:
1. `src/components/common/TimezoneBadge.vue`
2. `src/components/common/NotificationBadge.vue`
3. `src/composables/useNotificationPolling.js`
4. `src/tests/helpers/format.test.js`
5. `src/tests/composables/useNotificationPolling.test.js`
6. `e2e/timezone-display.spec.js`
7. `e2e/notification-polling.spec.js`
8. `e2e/payroll-reopen-confirmation.spec.js`
9. `e2e/payroll-reopen-concurrent.spec.js`
10. `e2e/timezone-dst.spec.js`
11. `docs/testing/manual-test-phase6a.md`

## Files to Modify (13 files)

**Backend (6)**:
1. `app/Models/StaffMemberProfile.php`
2. `app/Repositories/AuthRepository.php`
3. `app/Http/Resources/UserResource.php`
4. `routes/api.php`
5. `app/Http/Controllers/NotificationController.php`
6. `app/Providers/RepositoryServiceProvider.php`

**Frontend (7)**:
1. `src/stores/auth.js`
2. `src/helpers/format.js`
3. `src/layouts/Admin.vue`
4. `src/components/admin/Header.vue`
5. `src/views/admin/payroll/PayrollDetail.vue`
6. `src/views/admin/attendance/AttendanceList.vue`
7. `src/views/staff-member/attendance/MyAttendance.vue`

---

**END OF PLAN**
