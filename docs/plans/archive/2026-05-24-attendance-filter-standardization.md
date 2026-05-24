# Attendance Filter Standardization Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add server-side status/date filters to all attendance admin endpoints, remove client-side filtering hacks, and standardize HybridScheduleList to use shared components.

**Architecture:** Backend-first approach — add filter params to FormRequests + Repositories + Interfaces, then update FE stores to pass new params, then update views to use server-side filters via SearchFilter component.

**Tech Stack:** Laravel 12 (PHP 8.2), Vue 3 Composition API, Pinia stores, Pest tests, Vitest

---

## File Structure

### Backend (team-sync-be)

| Action | File | Responsibility |
|--------|------|---------------|
| Modify | `app/Http/Requests/AttendancePaginatedListRequest.php` | Add `status` validation rule |
| Modify | `app/Http/Requests/LeaveRequestPaginatedListRequest.php` | Add `status`, `date_from`, `date_to` rules |
| Create | `app/Http/Requests/HybridScheduleListRequest.php` | New FormRequest: search, status, per_page |
| Create | `app/Http/Requests/OvertimeListRequest.php` | New FormRequest: status, overtime_type, date_from, date_to, per_page, search |
| Modify | `app/Interfaces/AttendanceRepositoryInterface.php` | Add `?string $status` param to `getAllPaginated` |
| Modify | `app/Interfaces/LeaveRequestRepositoryInterface.php` | Add `?string $status`, `?string $dateFrom`, `?string $dateTo` params |
| Modify | `app/Interfaces/HybridWorkScheduleRepositoryInterface.php` | Add `?string $search`, `?string $status` params |
| Modify | `app/Repositories/AttendanceRepository.php` | Filter by status in `getAllPaginated` |
| Modify | `app/Repositories/LeaveRequestRepository.php` | Filter by status + date range |
| Modify | `app/Repositories/HybridWorkScheduleRepository.php` | Add search + status filter |
| Modify | `app/Http/Controllers/AttendanceController.php` | Pass `status` from request |
| Modify | `app/Http/Controllers/LeaveRequestController.php` | Pass `status`, `date_from`, `date_to` |
| Modify | `app/Http/Controllers/HybridWorkScheduleController.php` | Use FormRequest, pass search + status |
| Modify | `app/Http/Controllers/OvertimeController.php` | Use FormRequest instead of raw query |
| Create | `tests/Feature/Attendance/AttendanceFilterTest.php` | Test status filter on paginated endpoint |
| Create | `tests/Feature/Attendance/LeaveRequestFilterTest.php` | Test status + date filters |
| Create | `tests/Feature/Attendance/HybridScheduleFilterTest.php` | Test search + status filters |
| Create | `tests/Feature/Attendance/OvertimeFormRequestTest.php` | Test FormRequest validation |

### Frontend (team-sync-fe)

| Action | File | Responsibility |
|--------|------|---------------|
| Modify | `src/stores/attendance.js` | Pass `status` param in fetchAllPaginated |
| Modify | `src/stores/leaveRequest.js` | Normalize param construction (explicit like attendance.js) |
| Modify | `src/stores/hybridSchedule.js` | Pass `status` param |
| Modify | `src/views/admin/attendance/AttendanceRecordList.vue` | Add status filter to SearchFilter |
| Modify | `src/views/admin/attendance/LeaveRequestList.vue` | Remove client-side `filteredLeaveRequests`, use server-side status |
| Modify | `src/views/admin/attendance/HybridScheduleList.vue` | Use DataTableCard + EmployeeCell, remove dual loading, add status filter, remove client-side override filter |
| Modify | `src/views/admin/attendance/OvertimeManagement.vue` | Remove arrow wrapper on fetchFn |

---

## Task 1: BE — Add status filter to Attendance paginated endpoint

**Files:**
- Modify: `app/Http/Requests/AttendancePaginatedListRequest.php`
- Modify: `app/Interfaces/AttendanceRepositoryInterface.php`
- Modify: `app/Repositories/AttendanceRepository.php`
- Modify: `app/Http/Controllers/AttendanceController.php`
- Create: `tests/Feature/Attendance/AttendanceFilterTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Attendance/AttendanceFilterTest.php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->hrUser = User::factory()->create();
        $this->hrUser->assignRole('hr');
    }

    public function test_can_filter_attendances_by_status(): void
    {
        $staff = StaffMemberProfile::factory()->create();

        Attendance::factory()->create([
            'staff_member_id' => $staff->id,
            'status' => 'present',
        ]);
        Attendance::factory()->create([
            'staff_member_id' => $staff->id,
            'status' => 'late',
        ]);
        Attendance::factory()->create([
            'staff_member_id' => $staff->id,
            'status' => 'absent',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/attendances/all/paginated?row_per_page=15&status=late');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('late', $data[0]['status']);
    }

    public function test_returns_all_when_no_status_filter(): void
    {
        $staff = StaffMemberProfile::factory()->create();

        Attendance::factory()->count(3)->create([
            'staff_member_id' => $staff->id,
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/attendances/all/paginated?row_per_page=15');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(3, $data);
    }

    public function test_validates_status_value(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/attendances/all/paginated?row_per_page=15&status=invalid_status');

        $response->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Feature/Attendance/AttendanceFilterTest.php --filter=test_can_filter_attendances_by_status`
Expected: FAIL (status param not handled)

- [ ] **Step 3: Add status validation to FormRequest**

```php
// app/Http/Requests/AttendancePaginatedListRequest.php
public function rules(): array
{
    return [
        'search' => 'nullable|string',
        'row_per_page' => 'required|integer|min:1',
        'status' => 'nullable|string|in:present,late,absent,half_day,sick_leave,annual_leave',
    ];
}
```

- [ ] **Step 4: Update Interface**

```php
// app/Interfaces/AttendanceRepositoryInterface.php
public function getAllPaginated(
    ?string $search,
    int $rowPerPage,
    ?string $status = null
);
```

- [ ] **Step 5: Update Repository**

```php
// app/Repositories/AttendanceRepository.php
public function getAllPaginated(
    ?string $search,
    int $rowPerPage,
    ?string $status = null
): LengthAwarePaginator {
    $query = $this->getAll(
        $search,
        null,
        null,
        false
    );

    if ($status !== null && $status !== '') {
        $query->where('status', $status);
    }

    return $query->paginate($rowPerPage);
}
```

- [ ] **Step 6: Update Controller**

```php
// app/Http/Controllers/AttendanceController.php — getAllPaginated method
public function getAllPaginated(AttendancePaginatedListRequest $request): JsonResponse
{
    $attendances = $this->attendanceRepository->getAllPaginated(
        $request->validated('search'),
        $request->validated('row_per_page'),
        $request->validated('status')
    );

    // ... rest unchanged
}
```

- [ ] **Step 7: Run tests**

Run: `cd team-sync-be && composer test -- tests/Feature/Attendance/AttendanceFilterTest.php`
Expected: All 3 tests PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/AttendancePaginatedListRequest.php \
        app/Interfaces/AttendanceRepositoryInterface.php \
        app/Repositories/AttendanceRepository.php \
        app/Http/Controllers/AttendanceController.php \
        tests/Feature/Attendance/AttendanceFilterTest.php
git commit -m "feat(attendance): add status filter to paginated endpoint"
```

---

## Task 2: BE — Add status + date filters to LeaveRequest paginated endpoint

**Files:**
- Modify: `app/Http/Requests/LeaveRequestPaginatedListRequest.php`
- Modify: `app/Interfaces/LeaveRequestRepositoryInterface.php`
- Modify: `app/Repositories/LeaveRequestRepository.php`
- Modify: `app/Http/Controllers/LeaveRequestController.php`
- Create: `tests/Feature/Attendance/LeaveRequestFilterTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Attendance/LeaveRequestFilterTest.php

namespace Tests\Feature\Attendance;

use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveRequestFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->hrUser = User::factory()->create();
        $this->hrUser->assignRole('hr');
    }

    public function test_can_filter_leave_requests_by_status(): void
    {
        $staff = StaffMemberProfile::factory()->create();

        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'status' => 'pending',
        ]);
        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&status=pending');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('pending', $data[0]['status']);
    }

    public function test_can_filter_leave_requests_by_date_range(): void
    {
        $staff = StaffMemberProfile::factory()->create();

        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-17',
        ]);
        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
        ]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_validates_status_value(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&status=invalid');

        $response->assertUnprocessable();
    }

    public function test_validates_date_format(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&date_from=not-a-date');

        $response->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Feature/Attendance/LeaveRequestFilterTest.php --filter=test_can_filter_leave_requests_by_status`
Expected: FAIL

- [ ] **Step 3: Update FormRequest**

```php
// app/Http/Requests/LeaveRequestPaginatedListRequest.php
public function rules(): array
{
    return [
        'search' => 'nullable|string',
        'row_per_page' => 'required|integer|min:1',
        'status' => 'nullable|string|in:pending,approved,rejected',
        'date_from' => 'nullable|date_format:Y-m-d',
        'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
    ];
}
```

- [ ] **Step 4: Update Interface**

```php
// app/Interfaces/LeaveRequestRepositoryInterface.php
public function getAllPaginated(
    ?string $search,
    int $rowPerPage,
    ?string $status = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
);
```

- [ ] **Step 5: Update Repository**

```php
// app/Repositories/LeaveRequestRepository.php
public function getAllPaginated(
    ?string $search,
    int $rowPerPage,
    ?string $status = null,
    ?string $dateFrom = null,
    ?string $dateTo = null
) {
    $query = $this->getAll(
        $search,
        null,
        false
    );

    if ($status !== null && $status !== '') {
        $query->where('status', $status);
    }

    if ($dateFrom !== null && $dateFrom !== '') {
        $query->where('start_date', '>=', $dateFrom);
    }

    if ($dateTo !== null && $dateTo !== '') {
        $query->where('start_date', '<=', $dateTo);
    }

    return $query->paginate($rowPerPage);
}
```

- [ ] **Step 6: Update Controller**

```php
// app/Http/Controllers/LeaveRequestController.php — getAllPaginated method
public function getAllPaginated(LeaveRequestPaginatedListRequest $request): JsonResponse
{
    $leaveRequests = $this->leaveRequestRepository->getAllPaginated(
        $request->validated('search'),
        $request->validated('row_per_page'),
        $request->validated('status'),
        $request->validated('date_from'),
        $request->validated('date_to')
    );

    // ... rest unchanged (PaginateResource wrapping)
}
```

- [ ] **Step 7: Run tests**

Run: `cd team-sync-be && composer test -- tests/Feature/Attendance/LeaveRequestFilterTest.php`
Expected: All 4 tests PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/LeaveRequestPaginatedListRequest.php \
        app/Interfaces/LeaveRequestRepositoryInterface.php \
        app/Repositories/LeaveRequestRepository.php \
        app/Http/Controllers/LeaveRequestController.php \
        tests/Feature/Attendance/LeaveRequestFilterTest.php
git commit -m "feat(leave-request): add status and date range filters to paginated endpoint"
```

---

## Task 3: BE — Add search + status filter to HybridSchedule endpoint

**Files:**
- Create: `app/Http/Requests/HybridScheduleListRequest.php`
- Modify: `app/Interfaces/HybridWorkScheduleRepositoryInterface.php`
- Modify: `app/Repositories/HybridWorkScheduleRepository.php`
- Modify: `app/Http/Controllers/HybridWorkScheduleController.php`
- Create: `tests/Feature/Attendance/HybridScheduleFilterTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Attendance/HybridScheduleFilterTest.php

namespace Tests\Feature\Attendance;

use App\Models\HybridWorkSchedule;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridScheduleFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->hrUser = User::factory()->create();
        $this->hrUser->assignRole('hr');
    }

    public function test_can_search_hybrid_schedules_by_employee_name(): void
    {
        $user1 = User::factory()->create(['name' => 'Alice Johnson']);
        $staff1 = StaffMemberProfile::factory()->create(['user_id' => $user1->id]);
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff1->id]);

        $user2 = User::factory()->create(['name' => 'Bob Smith']);
        $staff2 = StaffMemberProfile::factory()->create(['user_id' => $user2->id]);
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff2->id]);

        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/hybrid-schedules?per_page=15&search=Alice');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_can_filter_hybrid_schedules_by_override_status(): void
    {
        $staff = StaffMemberProfile::factory()->create();
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff->id]);

        // Note: status filter applies to override requests, not base schedules
        // When status=pending is passed, only schedules with pending overrides are shown
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/hybrid-schedules?per_page=15');

        $response->assertOk();
    }

    public function test_validates_per_page_is_integer(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/hybrid-schedules?per_page=abc');

        $response->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Feature/Attendance/HybridScheduleFilterTest.php --filter=test_can_search`
Expected: FAIL (search param ignored)

- [ ] **Step 3: Create FormRequest**

```php
<?php
// app/Http/Requests/HybridScheduleListRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HybridScheduleListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

- [ ] **Step 4: Update Interface**

```php
// app/Interfaces/HybridWorkScheduleRepositoryInterface.php
public function getSchedulesPaginated(
    int $perPage,
    ?string $search = null,
    ?string $status = null
);
```

- [ ] **Step 5: Update Repository**

```php
// app/Repositories/HybridWorkScheduleRepository.php
public function getSchedulesPaginated(
    int $perPage,
    ?string $search = null,
    ?string $status = null
) {
    $query = HybridWorkSchedule::with(['staffMember.user'])
        ->orderBy('created_at', 'desc');

    if ($search !== null && $search !== '') {
        $query->whereHas('staffMember.user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    }

    if ($status !== null && $status !== '') {
        $query->whereHas('overrides', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    return $query->paginate($perPage);
}
```

- [ ] **Step 6: Update Controller**

```php
// app/Http/Controllers/HybridWorkScheduleController.php
use App\Http\Requests\HybridScheduleListRequest;

public function index(HybridScheduleListRequest $request): JsonResponse
{
    $schedules = $this->repository->getSchedulesPaginated(
        (int) $request->validated('per_page', 15),
        $request->validated('search'),
        $request->validated('status')
    );

    $schedules->setCollection($schedules->getCollection()->map(
        fn (HybridWorkSchedule $schedule): array => (new HybridWorkScheduleResource($schedule))->resolve($request)
    ));

    return response()->json([
        'success' => true,
        'data' => $schedules,
    ]);
}
```

- [ ] **Step 7: Run tests**

Run: `cd team-sync-be && composer test -- tests/Feature/Attendance/HybridScheduleFilterTest.php`
Expected: All 3 tests PASS

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/HybridScheduleListRequest.php \
        app/Interfaces/HybridWorkScheduleRepositoryInterface.php \
        app/Repositories/HybridWorkScheduleRepository.php \
        app/Http/Controllers/HybridWorkScheduleController.php \
        tests/Feature/Attendance/HybridScheduleFilterTest.php
git commit -m "feat(hybrid-schedule): add search and status filter to paginated endpoint"
```

---

## Task 4: BE — Add FormRequest to Overtime index endpoint

**Files:**
- Create: `app/Http/Requests/OvertimeListRequest.php`
- Modify: `app/Http/Controllers/OvertimeController.php`
- Create: `tests/Feature/Attendance/OvertimeFormRequestTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Attendance/OvertimeFormRequestTest.php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeFormRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $hrUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->hrUser = User::factory()->create();
        $this->hrUser->assignRole('hr');
    }

    public function test_validates_status_value(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/overtime?per_page=15&status=invalid_status');

        $response->assertUnprocessable();
    }

    public function test_validates_date_format(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/overtime?per_page=15&date_from=not-a-date');

        $response->assertUnprocessable();
    }

    public function test_validates_per_page_is_integer(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/overtime?per_page=abc');

        $response->assertUnprocessable();
    }

    public function test_accepts_valid_params(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/overtime?per_page=15&status=pending&date_from=2026-01-01&date_to=2026-12-31');

        $response->assertOk();
    }

    public function test_accepts_search_param(): void
    {
        $response = $this->actingAs($this->hrUser)
            ->getJson('/api/v1/overtime?per_page=15&search=John');

        $response->assertOk();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Feature/Attendance/OvertimeFormRequestTest.php --filter=test_validates_status_value`
Expected: FAIL (no validation — raw query params accepted)

- [ ] **Step 3: Create FormRequest**

```php
<?php
// app/Http/Requests/OvertimeListRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OvertimeListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'staff_member_id' => 'nullable|integer|exists:staff_member_profiles,id',
            'overtime_type' => 'nullable|string|in:weekday,weekend,holiday',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

- [ ] **Step 4: Update Controller**

```php
// app/Http/Controllers/OvertimeController.php
use App\Http\Requests\OvertimeListRequest;

public function index(OvertimeListRequest $request): JsonResponse
{
    try {
        $records = $this->overtimeService->getAllPaginated(
            $request->validated('status'),
            $request->validated('staff_member_id') ? (int) $request->validated('staff_member_id') : null,
            $request->validated('overtime_type'),
            $request->validated('date_from'),
            $request->validated('date_to'),
            (int) ($request->validated('per_page') ?? 15)
        );

        return ResponseHelper::jsonResponse(
            true,
            'Overtime Records Retrieved Successfully',
            PaginateResource::make($records, OvertimeRecordResource::class),
            200
        );
    } catch (\Throwable $e) {
        Log::error('OvertimeController@index Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

        return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
    }
}
```

- [ ] **Step 5: Add search support to OvertimeRepository**

The `OvertimeRepository::getAllPaginated` already filters by status, staffMemberId, overtimeType, dateFrom, dateTo. Add search by employee name:

```php
// app/Repositories/OvertimeRepository.php — inside getAllPaginated, after existing filters
// Add $search parameter to method signature:
public function getAllPaginated(
    ?string $status,
    ?int $staffMemberId,
    ?string $overtimeType,
    ?string $dateFrom,
    ?string $dateTo,
    int $perPage = 15,
    ?string $search = null
): LengthAwarePaginator {
    $query = OvertimeRecord::with(['staffMember.user', 'approvedByUser'])
        ->orderByDesc('date');

    if ($search !== null && $search !== '') {
        $query->whereHas('staffMember.user', function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    }

    // ... existing filters unchanged ...

    return $query->paginate($perPage);
}
```

Also update `OvertimeRepositoryInterface.php` and `OvertimeService.php` to pass `$search`:

```php
// app/Interfaces/OvertimeRepositoryInterface.php
public function getAllPaginated(
    ?string $status,
    ?int $staffMemberId,
    ?string $overtimeType,
    ?string $dateFrom,
    ?string $dateTo,
    int $perPage = 15,
    ?string $search = null
): LengthAwarePaginator;

// app/Services/OvertimeService.php
public function getAllPaginated(
    ?string $status,
    ?int $staffMemberId,
    ?string $overtimeType,
    ?string $dateFrom,
    ?string $dateTo,
    int $perPage = 15,
    ?string $search = null
): LengthAwarePaginator {
    return $this->overtimeRepository->getAllPaginated(
        $status, $staffMemberId, $overtimeType, $dateFrom, $dateTo, $perPage, $search
    );
}
```

Update controller to pass search:

```php
// In OvertimeController@index, add search param:
$records = $this->overtimeService->getAllPaginated(
    $request->validated('status'),
    $request->validated('staff_member_id') ? (int) $request->validated('staff_member_id') : null,
    $request->validated('overtime_type'),
    $request->validated('date_from'),
    $request->validated('date_to'),
    (int) ($request->validated('per_page') ?? 15),
    $request->validated('search')
);
```

- [ ] **Step 6: Run tests**

Run: `cd team-sync-be && composer test -- tests/Feature/Attendance/OvertimeFormRequestTest.php`
Expected: All 5 tests PASS

- [ ] **Step 7: Run full BE test suite**

Run: `cd team-sync-be && composer test`
Expected: All tests PASS (no regressions)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/OvertimeListRequest.php \
        app/Http/Controllers/OvertimeController.php \
        app/Repositories/OvertimeRepository.php \
        app/Interfaces/OvertimeRepositoryInterface.php \
        app/Services/OvertimeService.php \
        tests/Feature/Attendance/OvertimeFormRequestTest.php
git commit -m "feat(overtime): add FormRequest validation and search support"
```

---

## Task 5: FE — Add status filter to AttendanceRecordList + update store

**Files:**
- Modify: `src/stores/attendance.js` — pass `status` param
- Modify: `src/views/admin/attendance/AttendanceRecordList.vue` — add status filter dropdown

- [ ] **Step 1: Update store to pass status param**

```js
// src/stores/attendance.js — fetchAllPaginated
async fetchAllPaginated(params = {}) {
    this.loading = true;
    this.error = null;
    try {
        const response = await axiosInstance.get("attendances/all/paginated", {
            params: {
                page: params.page || 1,
                search: params.search || "",
                row_per_page: params.row_per_page || 10,
                status: params.status || "",
            },
        });
        // ... rest unchanged
    }
}
```

- [ ] **Step 2: Add status filter to AttendanceRecordList**

In `useSearchFilter` setup, add `status` to defaultFilters:

```js
const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: store.fetchAllPaginated,
});
```

In template, add `filters` prop to `SearchFilter`:

```vue
<SearchFilter
    placeholder="Search by employee name..."
    :filters="[
        {
            key: 'status',
            label: 'Status',
            options: [
                { value: 'present', label: 'Present' },
                { value: 'late', label: 'Late' },
                { value: 'absent', label: 'Absent' },
                { value: 'half_day', label: 'Half Day' },
                { value: 'sick_leave', label: 'Sick Leave' },
                { value: 'annual_leave', label: 'Annual Leave' },
            ],
        },
    ]"
    @search="handleSearch"
    @reset="handleReset"
/>
```

- [ ] **Step 3: Run FE tests**

Run: `cd team-sync-fe && bun run test`
Expected: All tests PASS

- [ ] **Step 4: Commit**

```bash
git add team-sync-fe/src/stores/attendance.js \
        team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue
git commit -m "feat(fe): add status filter to attendance record list"
```

---

## Task 6: FE — Remove client-side filtering from LeaveRequestList + use server-side status

**Files:**
- Modify: `src/stores/leaveRequest.js` — normalize param construction
- Modify: `src/views/admin/attendance/LeaveRequestList.vue` — remove `leaveStatusFilter` + `filteredLeaveRequests`

- [ ] **Step 1: Normalize leaveRequest store param construction**

```js
// src/stores/leaveRequest.js — fetchLeaveRequestsPaginated
async fetchLeaveRequestsPaginated(params = {}) {
    this.loading = true;
    this.error = null;

    try {
        const response = await axiosInstance.get("leave-requests/all/paginated", {
            params: {
                page: params.page || 1,
                search: params.search || "",
                row_per_page: params.row_per_page || 10,
                status: params.status || "",
                date_from: params.date_from || "",
                date_to: params.date_to || "",
            },
        });
        const paginator = response.data.data;
        this.leaveRequests = paginator.data;
        this.meta = {
            current_page: paginator.current_page,
            last_page: paginator.last_page,
            per_page: paginator.per_page,
            total: paginator.total,
            from: paginator.from,
            to: paginator.to,
        };
        return paginator;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    } finally {
        this.loading = false;
    }
},
```

- [ ] **Step 2: Remove client-side filtering from LeaveRequestList**

Remove these lines from `<script setup>`:
```js
// DELETE these:
const leaveStatusFilter = ref("");

const filteredLeaveRequests = computed(() => {
    if (!leaveStatusFilter.value) {
        return leaveRequests.value || [];
    }
    return (leaveRequests.value || []).filter((request) => request.status === leaveStatusFilter.value);
});
```

- [ ] **Step 3: Update template to use `leaveRequests` directly**

Replace all `filteredLeaveRequests` references in template with `leaveRequests`:

```vue
<!-- TableStateRows -->
<TableStateRows
    :loading="loading"
    :empty="!leaveRequests || leaveRequests.length === 0"
    :colspan="7"
    ...
/>

<!-- Data rows -->
<template v-if="leaveRequests && leaveRequests.length > 0 && !loading">
<tr v-for="request in leaveRequests" :key="request.id" ...>
```

- [ ] **Step 4: Remove `@update:modelValue` handler from SearchFilter**

The SearchFilter already sends `status` server-side via `useSearchFilter`. Remove the extra `@update:modelValue` that was syncing `leaveStatusFilter`:

```vue
<SearchFilter
    placeholder="Search by employee name..."
    :filters="[
        {
            key: 'status',
            label: 'Status',
            options: [
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
            ],
        },
    ]"
    @search="handleSearch"
    @reset="handleReset"
/>
```

- [ ] **Step 5: Fix useSearchFilter defaultFilters**

Ensure `date_from` and `date_to` are included in defaultFilters so DatePagination still works:

```js
const { filters, serverOptions, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "", date_from: null, date_to: null },
    fetchFn: store.fetchLeaveRequestsPaginated,
});
```

- [ ] **Step 6: Run FE tests**

Run: `cd team-sync-fe && bun run test`
Expected: All tests PASS

- [ ] **Step 7: Commit**

```bash
git add team-sync-fe/src/stores/leaveRequest.js \
        team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue
git commit -m "feat(fe): remove client-side leave request filtering, use server-side status filter"
```

---

## Task 7: FE — Standardize HybridScheduleList (DataTableCard, EmployeeCell, remove dual loading)

**Files:**
- Modify: `src/stores/hybridSchedule.js` — pass `status` param
- Modify: `src/views/admin/attendance/HybridScheduleList.vue` — major refactor

This is the largest FE task. HybridScheduleList currently:
1. Uses manual card wrapper + manual Pagination (should use DataTableCard)
2. Has outer loading spinner + TableStateRows inside tbody (dual loading)
3. Uses raw text for employee name (should use EmployeeCell)
4. Filters overrides client-side to `status === 'pending'` (should use server-side)
5. Has no status filter dropdown

- [ ] **Step 1: Update store to pass status param**

```js
// src/stores/hybridSchedule.js — fetchAllPaginated
async fetchAllPaginated(params = {}) {
    this.loading = true;
    this.error = null;
    try {
        const response = await axiosInstance.get("hybrid-schedules", {
            params: {
                page: params.page || 1,
                search: params.search || "",
                row_per_page: params.row_per_page || 10,
                status: params.status || "",
            },
        });
        // ... rest unchanged
    }
}
```

Note: The BE param is `per_page` not `row_per_page` for this endpoint. Check if the store already maps it. Current store sends `row_per_page` but BE reads `per_page`. After Task 3, the BE FormRequest validates `per_page`. Update store:

```js
params: {
    page: params.page || 1,
    search: params.search || "",
    per_page: params.row_per_page || 10,
    status: params.status || "",
},
```

- [ ] **Step 2: Remove outer loading/error divs**

Remove the manual loading spinner div (lines ~210-221) and error div. Let `TableStateRows` inside `DataTableCard` handle loading state.

- [ ] **Step 3: Replace manual card + Pagination with DataTableCard**

Replace:
```vue
<div class="bg-white rounded-2xl border border-brand-border overflow-hidden">
    <!-- table -->
    <div class="p-4 border-t border-brand-border bg-brand-border/10">
        <Pagination ... />
    </div>
</div>
```

With:
```vue
<DataTableCard :meta="meta" :loading="loading" @page-change="handlePageChange" @per-page-change="handlePerPageChange">
    <!-- table content (thead + tbody) -->
</DataTableCard>
```

- [ ] **Step 4: Replace raw employee text with EmployeeCell**

In overrides table, replace:
```vue
<td class="px-6 py-4 text-sm font-semibold text-brand-dark">
    {{ override.employeeName }}
</td>
```

With:
```vue
<td class="px-6 py-4">
    <EmployeeCell
        :photo="override.employeePhoto"
        :name="override.employeeName"
        :subtitle="override.staffMemberId"
    />
</td>
```

Note: Check if `override.employeePhoto` and `override.staffMemberId` are available from the API resource. If not, use only `:name` prop.

- [ ] **Step 5: Remove client-side override filtering**

Remove the `overrideItems` computed that filters to `status === 'pending'`:
```js
// DELETE:
const overrideItems = computed(() => {
    return (paginatedSchedules.value || []).filter(s => s.status === 'pending');
});
```

Instead, add a status filter to SearchFilter and let the server filter:
```vue
<SearchFilter
    placeholder="Search hybrid schedules..."
    :filters="[
        {
            key: 'status',
            label: 'Override Status',
            options: [
                { value: 'pending', label: 'Pending' },
                { value: 'approved', label: 'Approved' },
                { value: 'rejected', label: 'Rejected' },
            ],
        },
    ]"
    @search="handleSearch"
    @reset="handleReset"
/>
```

- [ ] **Step 6: Update imports**

Remove: `Pagination` import
Add: `DataTableCard`, `EmployeeCell` imports

```js
import DataTableCard from "@/components/common/DataTableCard.vue";
import EmployeeCell from "@/components/common/EmployeeCell.vue";
// Remove: import Pagination from "@/components/common/Pagination.vue";
```

- [ ] **Step 7: Run FE tests**

Run: `cd team-sync-fe && bun run test`
Expected: All tests PASS

- [ ] **Step 8: Commit**

```bash
git add team-sync-fe/src/stores/hybridSchedule.js \
        team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue
git commit -m "feat(fe): standardize HybridScheduleList with DataTableCard, EmployeeCell, server-side filters"
```

---

## Task 8: FE — Minor cleanups (OvertimeManagement fetchFn, SearchFilter option shape)

**Files:**
- Modify: `src/views/admin/attendance/OvertimeManagement.vue` — remove arrow wrapper on fetchFn

- [ ] **Step 1: Remove unnecessary arrow wrapper**

In OvertimeManagement.vue, change:
```js
const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: (params) => store.fetchOvertimeRecords(params),
});
```

To:
```js
const { filters, fetchData, handleSearch, handleReset, handlePageChange, handlePerPageChange } = useSearchFilter({
    defaultFilters: { search: null, status: "" },
    fetchFn: store.fetchOvertimeRecords,
});
```

- [ ] **Step 2: Verify SearchFilter option shape consistency**

All SearchFilter `filters` arrays should use `{ value, label }` shape for options. Check LeaveRequestList — if it uses `{ id, name }`, change to `{ value, label }`:

```js
// Correct shape (all files should use this):
options: [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
]
```

- [ ] **Step 3: Run FE tests**

Run: `cd team-sync-fe && bun run test`
Expected: All tests PASS

- [ ] **Step 4: Commit**

```bash
git add team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue
git commit -m "chore(fe): cleanup fetchFn wrapper and filter option shape"
```

---

## Task 9: Full Integration Verification

- [ ] **Step 1: Run full BE test suite**

Run: `cd team-sync-be && composer test`
Expected: All tests PASS (1478+ tests)

- [ ] **Step 2: Run full FE test suite**

Run: `cd team-sync-fe && bun run test`
Expected: All tests PASS (1083+ tests)

- [ ] **Step 3: Manual smoke test (if dev server available)**

Start both servers:
```bash
cd team-sync-be && composer dev &
cd team-sync-fe && bun run dev &
```

Verify in browser:
1. Attendance Records tab — status dropdown filters correctly
2. Leave Requests tab — status filter works server-side (no client-side flash)
3. Overtime tab — search by employee name works
4. Hybrid Schedules tab — search + status filter works, DataTableCard renders correctly

- [ ] **Step 4: Final commit (if any fixes needed)**

```bash
git add -A
git commit -m "fix: integration fixes from smoke testing"
```

---

## Summary of Changes

### Backend (4 tasks)
| Endpoint | Before | After |
|----------|--------|-------|
| `GET /attendances/paginated` | search only | + status filter |
| `GET /leave-requests/paginated` | search only | + status, date_from, date_to |
| `GET /hybrid-schedules` | per_page only | + search, status (FormRequest) |
| `GET /overtime` | raw query params | FormRequest validation + search |

### Frontend (4 tasks)
| View | Before | After |
|------|--------|-------|
| AttendanceRecordList | No status filter | Status dropdown via SearchFilter |
| LeaveRequestList | Client-side double-filter | Server-side only, cleaner code |
| HybridScheduleList | Manual card, dual loading, no EmployeeCell, client-side filter | DataTableCard, EmployeeCell, server-side filter |
| OvertimeManagement | Arrow wrapper on fetchFn | Direct reference |

### Lines Impact (estimated)
- BE: +180 lines (FormRequests, repo changes, tests)
- FE: -40 lines net (remove client-side filtering, add filter configs)
