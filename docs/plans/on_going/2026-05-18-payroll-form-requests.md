# Payroll FormRequests Extraction Implementation Plan

> **Status (2026-05-18):** EXECUTED. PR #43 https://github.com/hyaraxco/team-sync/pull/43 — awaiting CI + merge.

## Implementation Notes (post-execution)

Executed in 6 batches via subagent-driven-development pattern. All 18 commits squashed pending PR merge.

### Final stats
- Inline `$request->validate()` count: 14 → **0**
- BE test count: 99 passed (matches baseline) → 99 passed (5523 → 5649 assertions, +126 from new validation tests)
- Pint: clean
- New files: 4 FormRequests + 2 test files
- Modified files: 4 FormRequests + PayrollController + 9 existing payroll tests (test data fixes for tightened rules)

### Plan deviations applied
1. **`PayrollUpdateDetailRequest.updated_at`** relaxed from `date_format:Y-m-d H:i:s` to `nullable|date`. FE pre-flight found existing tests use `->toISOString()` (ISO-8601). Strict format would have broken regression suite.
2. **`PayrollGenerateReadinessRequest`** uses closure for future-month guard. `before_or_equal:today` doesn't apply to `Y-m` format input.
3. **`different:month1` rule** correctly omitted from `PayrollComparisonRequest` per plan (would smuggle behavior change).

### Per-batch summary
- Batch 1 (7 commits): 6 reuse-as-is FormRequests wired
- Batch 2 (4 commits): 3 new FormRequests created (GenerateReadiness, MarkAsPaid, Analytics)
- Batch 3 (3 commits): 2 patched (UpdateDetail, Reopen)
- Batch 4 (3 commits): 2 rewritten (Comparison, ExportReport) + baseline test
- Batch 5 (1 commit): PayrollIndexRequest (added validation where none existed)

### Code review verdict
Oracle review (Arsitek + Hasan personas): **Approve.** No critical/important issues. Two minor observations (kept `Request` import for `approvePayroll`/`resendNotifications` which use `$request->user()`; closure rule pattern in GenerateReadiness could use a comment).

---

> **For agentic workers:** REQUIRED SUB-SKILL: Use `subagent-driven-development` (recommended) or `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace every `$request->validate([...])` in `team-sync-be/app/Http/Controllers/PayrollController.php` with typed FormRequest injection, without changing response shape or business logic. **Intentional rule tightening allowed** where it aligns with domain invariants (IDR integer-only, reason length cap, payment date guard, optimistic-lock format).

**Architecture:** Mechanical extraction in 6 batches ordered by risk (lowest first). Reuse existing FormRequests where shape matches, patch where rules diverge by tightening, rewrite where shape conflicts. State machine guards stay in Repository — FormRequest only validates input shape.

---

## ⚠️ CRITICAL: `$validated` Variable Migration Pattern

Every method targeted by this plan currently uses `$validated = $request->validate([...])` and references `$validated['key']` later in the body. **Removing the `$request->validate()` call without restoring the variable breaks every method (undefined variable → 500 error).**

### Migration template (MUST follow per task)

For each task that swaps `Request` → specific FormRequest, the controller body MUST be updated:

**Before extraction:**
```php
public function methodName(Request $request, ...): JsonResponse
{
    $validated = $request->validate([
        'field' => 'rule',
    ]);

    // ...uses $validated['field']...
}
```

**After extraction (preferred — minimal body change):**
```php
public function methodName(SpecificFormRequest $request, ...): JsonResponse
{
    $validated = $request->validated();

    // ...uses $validated['field'] (unchanged)...
}
```

This preserves all `$validated['key']` references in the body. Only the variable source changes.

### Pre-flight grep (run before each task)

```bash
grep -n "\$validated" team-sync-be/app/Http/Controllers/PayrollController.php
```

Confirm every reference inside the target method survives the extraction. If a reference exists outside the validated set (e.g., method reads `$validated['undeclared_key']`), the FormRequest must include that field or the body must be fixed.

**Tech Stack:** Laravel 12 + Pest 4 + Laravel Pint + Spatie Permission + SQLite :memory: (tests).

---

## Party Mode Synthesis (2026-05-18)

### Round 1 — Plan creation

- 🏗️ Arsitek — Sequence low-risk first; rewrite mismatched FormRequests, don't force-fit; hard guardrail vs PayrollService creep.
- 🧪 Fitri — Add ~45 validation-failure tests in dedicated `PayrollControllerValidationTest.php`; cover untested `getComparison` first; verify side-effect ordering with `Queue::fake()` + DB asserts.
- ⚙️ Dede — Use closure for `before_or_equal:now()`; rewrite `exportReport` and `getComparison` in place; keep `authorize(): true`; preserve `numeric`→`integer` switch (IDR is integer-only); thin Pest tests with `assertJsonValidationErrors`.

### Round 2 — Plan maturity review

- 🔒 Hasan — Auth model sound; tighten `updated_at` to `date_format:Y-m-d H:i:s`; add `before_or_equal:today` on `payment_date`; fix risk-table pointer to `PayrollController::middleware()` lines 79-89 (not routes/api.php).
- 🧪 Fitri — TDD strong; add reopen DB-state assertion; add current-month boundary test; add float-months edge case; add limit-zero boundary.
- 🏗️ Arsitek — Critical: `$validated` migration template missing from every task. Drop `different:month1` (YAGNI, not in inline rules). Add FE pre-flight grep for Batch 5.

### Agreement after Round 2
All revisions integrated. Plan is mature and execute-ready.

### Decision
Execute in 6 batches with separate dev commits, squashed into one PR commit at the end. Test-first per FormRequest. No PayrollService work in this PR. Follow `$validated` migration template at top of plan for every signature swap.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `team-sync-be/app/Http/Controllers/PayrollController.php` | Modify: replace inline `validate()` with FormRequest type-hints; remove `Illuminate\Http\Request` import where possible. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollListRequest.php` | Reuse: matches `getAllPaginated` rules. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollDetailsRequest.php` | Reuse: matches `getDetails` rules. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollReconciliationRequest.php` | Reuse: matches `getReconciliation` rules. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollSalaryMonthRequest.php` | Reuse: matches `readinessDashboard` and `readinessTeamSummary` rules. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollGenerateRequest.php` | Reuse: matches `generate` rules. |
| `team-sync-be/app/Http/Requests/ResolveReconciliationExceptionRequest.php` | Reuse: matches `resolveReconciliationException` rules. Leave in root namespace (out-of-scope rename). |
| `team-sync-be/app/Http/Requests/Payroll/PayrollGenerateReadinessRequest.php` | **Create**: `salary_month: required\|date_format:Y-m\|<closure: not future>`. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollMarkAsPaidRequest.php` | **Create**: `payment_date: required\|date\|before_or_equal:today`. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollAnalyticsRequest.php` | **Create**: `months: nullable\|integer\|min:1\|max:24`. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollIndexRequest.php` | **Create**: `search: nullable\|string\|max:255`, `limit: nullable\|integer\|min:1\|max:100`. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollUpdateDetailRequest.php` | **Patch**: change `numeric`→`integer` (controller already uses `integer` inline; FormRequest file lags); ensure `updated_at: nullable\|date_format:Y-m-d H:i:s` (tightened from inline `string` for optimistic-lock safety). |
| `team-sync-be/app/Http/Requests/Payroll/PayrollReopenRequest.php` | **Patch**: add `max:500` on `reason`. |
| `team-sync-be/app/Http/Requests/Payroll/PayrollComparisonRequest.php` | **Rewrite**: replace `months[]` shape with `month1`+`month2` flat fields (matches inline rules verbatim — no extra constraints). |
| `team-sync-be/app/Http/Requests/Payroll/PayrollExportReportRequest.php` | **Rewrite**: add `required_if`, `digits:4`, `report_type` enum, `period_type` enum. |
| `team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php` | **Create**: ~45 validation-failure tests across 14 methods. |
| `team-sync-be/tests/Feature/Payroll/PayrollComparisonTest.php` | **Create**: happy-path coverage for `getComparison` (currently untested). |

---

## Pre-Execution Setup

### Task 0: Worktree, Baseline, Branch

**Files:**
- Workspace: new isolated worktree under `~/.config/superpowers/worktrees/team-sync/payroll-form-requests/` or `.worktrees/payroll-form-requests/` if project-local exists.

- [ ] **Step 1: Verify PR #42 merged**

```bash
gh pr view 42 --json state,mergedAt
```
Expected: `"state":"MERGED"`. If still open, abort plan execution.

- [ ] **Step 2: Sync local main**

```bash
cd /Users/hyarax/Documents/project/team-sync
git fetch origin
git checkout main
git pull --ff-only origin main
```

- [ ] **Step 3: Create isolated worktree from main**

```bash
git worktree add /var/folders/0n/49nvf9ss223dhmr5_b26_vm40000gn/T/opencode/team-sync-worktrees/payroll-form-requests -b chore/payroll-form-requests origin/main
```

- [ ] **Step 4: Install BE deps in worktree**

```bash
cd /var/folders/0n/49nvf9ss223dhmr5_b26_vm40000gn/T/opencode/team-sync-worktrees/payroll-form-requests/team-sync-be
composer install
```

- [ ] **Step 5: Record baseline pass count**

```bash
composer test 2>&1 | tail -3
```

Record the line `Tests: X passed, Y warnings (Z assertions)`. Save the X number for regression comparison after each batch.

- [ ] **Step 6: Targeted payroll baseline**

```bash
php artisan config:clear --ansi && php artisan test --filter=Payroll 2>&1 | tail -3
```

Record baseline payroll pass count.

---

## Batch 1 — Reuse-as-is Group (6 methods)

Six methods where existing FormRequest already matches the inline rules verbatim. Pure type-hint swap.

### Task 1.1: Wire `getAllPaginated` to `PayrollListRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 114-122
- Test: `team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php` (create)

- [ ] **Step 1: Create validation test file with first failing test**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollControllerValidationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'payroll-list', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-edit', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-process', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-statistics', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-readiness-view', 'guard_name' => 'sanctum']);

        $role = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'sanctum']);
        $role->givePermissionTo([
            'payroll-list', 'payroll-create', 'payroll-edit',
            'payroll-process', 'payroll-statistics', 'payroll-readiness-view',
        ]);

        $this->finance = User::factory()->create();
        $this->finance->assignRole('Finance');
    }

    public function test_get_all_paginated_rejects_non_integer_row_per_page(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/all/paginated?row_per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['row_per_page']);
    }
}
```

- [ ] **Step 2: Run test to verify RED**

```bash
php artisan config:clear --ansi && php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
```

Expected: FAIL with status 200 (current inline `validate()` allows `"abc"` because rule is `nullable|integer` — wait, that should already fail). If it passes, confirm rule already enforces integer.

If currently passes: skip to Step 3 (no extraction needed for this exact rule). If fails as 200: investigate. Most likely outcome: passes 422 already because inline `nullable|integer` rejects `"abc"` — that's fine, the test now locks the contract.

- [ ] **Step 3: Replace inline validate with FormRequest**

In `team-sync-be/app/Http/Controllers/PayrollController.php`, locate `getAllPaginated` (around line 114):

Before:
```php
public function getAllPaginated(Request $request): JsonResponse
{
    $validated = $request->validate([
        'search' => 'nullable|string',
        'row_per_page' => 'nullable|integer',
        'page' => 'nullable|integer',
    ]);

    // ...uses $validated['search'], $validated['row_per_page']...
}
```

After:
```php
public function getAllPaginated(PayrollListRequest $request): JsonResponse
{
    $validated = $request->validated();

    // ...uses $validated['search'], $validated['row_per_page']... (unchanged)
}
```

Add `use App\Http\Requests\Payroll\PayrollListRequest;` to imports.

> **`$validated` migration applied** per the Critical pattern at top of plan. Body references stay the same.

- [ ] **Step 4: Run targeted test to verify GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
```

Expected: PASS.

- [ ] **Step 5: Run payroll suite for regression**

```bash
php artisan test --filter=Payroll
```

Expected: pass count ≥ baseline.

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "refactor: wire PayrollListRequest to getAllPaginated"
```

### Task 1.2: Wire `getDetails` to `PayrollDetailsRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 157-164
- Test: append to `team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php`

- [ ] **Step 1: Append failing tests**

```php
public function test_get_details_rejects_per_page_below_10(): void
{
    Sanctum::actingAs($this->finance);

    $payrollId = 1; // Validation runs before route lookup; 404 only after pass
    $this->getJson("/api/v1/payrolls/{$payrollId}/details?per_page=5")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
}

public function test_get_details_rejects_per_page_above_100(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/1/details?per_page=200')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
}
```

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=get_details
```

Expected: PASS already (inline rule `min:10|max:100` already enforces this) — tests just lock the contract.

- [ ] **Step 3: Swap signature**

In `getDetails` (around line 157):

Before:
```php
public function getDetails(Request $request, string $id): JsonResponse
{
    $request->validate([
        'per_page' => 'nullable|integer|min:10|max:100',
        'page' => 'nullable|integer',
    ]);
    // ...
}
```

After:
```php
public function getDetails(PayrollDetailsRequest $request, string $id): JsonResponse
{
    // ...
}
```

Add import.

- [ ] **Step 4: Verify GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=get_details
php artisan test --filter=Payroll
```

- [ ] **Step 5: Commit**

```bash
git add team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "refactor: wire PayrollDetailsRequest to getDetails"
```

### Task 1.3: Wire `getReconciliation` to `PayrollReconciliationRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 183-190
- Test: append to `PayrollControllerValidationTest.php`

- [ ] **Step 1: Append failing tests**

```php
public function test_get_reconciliation_rejects_invalid_severity(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/1/reconciliation?severity=high')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['severity']);
}

public function test_get_reconciliation_rejects_type_exceeding_100_chars(): void
{
    Sanctum::actingAs($this->finance);

    $longType = str_repeat('a', 101);
    $this->getJson("/api/v1/payrolls/1/reconciliation?type={$longType}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
}
```

- [ ] **Step 2: Verify RED → GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=get_reconciliation
```

- [ ] **Step 3: Swap signature**

```php
public function getReconciliation(PayrollReconciliationRequest $request, string $id): JsonResponse
```

Add import.

- [ ] **Step 4: Verify GREEN + regression**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=get_reconciliation
php artisan test --filter=Payroll
```

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor: wire PayrollReconciliationRequest to getReconciliation"
```

### Task 1.4: Wire `readinessDashboard` and `readinessTeamSummary` to `PayrollSalaryMonthRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 229-236 + 785-791
- Test: append

- [ ] **Step 1: Append failing tests**

```php
public function test_readiness_dashboard_requires_salary_month(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/readiness-dashboard')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}

public function test_readiness_dashboard_rejects_invalid_format(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026/04')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}

public function test_readiness_team_summary_requires_salary_month(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/readiness-dashboard/team-summary')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}
```

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=readiness
```

- [ ] **Step 3: Swap both signatures**

`readinessDashboard`:
```php
public function readinessDashboard(PayrollSalaryMonthRequest $request): JsonResponse
```

`readinessTeamSummary`:
```php
public function readinessTeamSummary(PayrollSalaryMonthRequest $request): JsonResponse
```

Add import.

- [ ] **Step 4: Verify GREEN + regression**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
php artisan test --filter=Payroll
```

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor: wire PayrollSalaryMonthRequest to readiness endpoints"
```

### Task 1.5: Wire `generate` to `PayrollGenerateRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 255-262
- Test: append

- [ ] **Step 1: Append failing tests including queue dispatch ordering**

```php
public function test_generate_requires_salary_month(): void
{
    \Queue::fake();
    Sanctum::actingAs($this->finance);

    $this->postJson('/api/v1/payrolls/generate', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);

    \Queue::assertNothingPushed();
}

public function test_generate_rejects_invalid_date_format(): void
{
    \Queue::fake();
    Sanctum::actingAs($this->finance);

    $this->postJson('/api/v1/payrolls/generate', ['salary_month' => '04-2026'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);

    \Queue::assertNothingPushed();
}
```

- [ ] **Step 2: Verify RED → GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=generate
```

- [ ] **Step 3: Swap signature**

```php
public function generate(PayrollGenerateRequest $request): JsonResponse
```

Add import.

- [ ] **Step 4: Verify GREEN + regression — high-risk method**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
php artisan test --filter=Payroll
```

If `PayrollGenerateRulesTest` breaks, root-cause before continuing.

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor: wire PayrollGenerateRequest to generate"
```

### Task 1.6: Wire `resolveReconciliationException` to existing `ResolveReconciliationExceptionRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 814-823
- Test: append

- [ ] **Step 1: Append failing tests**

```php
public function test_resolve_reconciliation_exception_requires_resolution_action(): void
{
    Sanctum::actingAs($this->finance);

    $this->postJson('/api/v1/payrolls/1/reconciliation/resolve', [
        'staff_member_id' => 1,
        'exception_type' => 'missing_bank',
        'reason' => 'verified manually',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['resolution_action']);
}

public function test_resolve_reconciliation_exception_rejects_invalid_action(): void
{
    Sanctum::actingAs($this->finance);

    $this->postJson('/api/v1/payrolls/1/reconciliation/resolve', [
        'staff_member_id' => 1,
        'exception_type' => 'missing_bank',
        'resolution_action' => 'ignore',
        'reason' => 'verified manually',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['resolution_action']);
}
```

- [ ] **Step 2: Verify RED → GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=resolve_reconciliation
```

- [ ] **Step 3: Swap signature**

```php
public function resolveReconciliationException(ResolveReconciliationExceptionRequest $request, string $id): JsonResponse
```

Add `use App\Http\Requests\ResolveReconciliationExceptionRequest;` import (root namespace, not Payroll/).

- [ ] **Step 4: Verify GREEN + regression**

```bash
php artisan test --filter=Payroll
```

- [ ] **Step 5: Commit**

```bash
git commit -am "refactor: wire ResolveReconciliationExceptionRequest to resolveReconciliationException"
```

### Task 1.7: Run Pint and Full Suite Checkpoint

- [ ] **Step 1: Run Pint**

```bash
./vendor/bin/pint
```

Expected: `{"result":"pass"}`.

- [ ] **Step 2: Run full BE suite**

```bash
composer test 2>&1 | tail -3
```

Expected: pass count ≥ Task 0 baseline. Zero failures.

- [ ] **Step 3: Commit Pint fixes if any**

```bash
git status --short
git commit -am "chore: pint formatting after batch 1" || true
```

---

## Batch 2 — New Simple FormRequests

Three new FormRequest classes for methods with no existing matching FormRequest.

### Task 2.1: Create `PayrollGenerateReadinessRequest`

**Files:**
- Create: `team-sync-be/app/Http/Requests/Payroll/PayrollGenerateReadinessRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 208-214
- Test: append to `PayrollControllerValidationTest.php`

- [ ] **Step 1: Append failing tests**

```php
public function test_generate_readiness_requires_salary_month(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/generate-readiness')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}

public function test_generate_readiness_rejects_invalid_format(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-1')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}

public function test_generate_readiness_rejects_future_month(): void
{
    Sanctum::actingAs($this->finance);

    $futureMonth = now()->addMonths(2)->format('Y-m');
    $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$futureMonth}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['salary_month']);
}

public function test_generate_readiness_accepts_current_month(): void
{
    Sanctum::actingAs($this->finance);

    $currentMonth = now()->format('Y-m');
    // Validation must pass (closure uses `>`, not `>=`).
    // Endpoint may still 422 on domain logic — assert validation field NOT in errors.
    $response = $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$currentMonth}");

    if ($response->status() === 422) {
        $response->assertJsonMissingValidationErrors(['salary_month']);
    }
}
```

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=generate_readiness
```

Expected: passes already because inline rule has the same constraints. Tests lock contract.

- [ ] **Step 3: Create FormRequest**

`team-sync-be/app/Http/Requests/Payroll/PayrollGenerateReadinessRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PayrollGenerateReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salary_month' => [
                'required',
                'date_format:Y-m',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value > now()->format('Y-m')) {
                        $fail("The {$attribute} cannot be in the future.");
                    }
                },
            ],
        ];
    }
}
```

- [ ] **Step 4: Swap signature**

```php
public function generateReadiness(PayrollGenerateReadinessRequest $request): JsonResponse
```

Add import.

- [ ] **Step 5: Verify GREEN + regression**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
php artisan test --filter=Payroll
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollGenerateReadinessRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "feat: extract PayrollGenerateReadinessRequest with future-month guard"
```

### Task 2.2: Create `PayrollMarkAsPaidRequest`

**Files:**
- Create: `team-sync-be/app/Http/Requests/Payroll/PayrollMarkAsPaidRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 364-370
- Test: append

- [ ] **Step 1: Append failing tests including DB state assertion**

```php
public function test_mark_as_paid_requires_payment_date(): void
{
    Sanctum::actingAs($this->finance);

    // Need an approved payroll for state to matter
    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);

    $this->assertDatabaseHas('payrolls', [
        'id' => $payroll->id,
        'status' => 'approved',
    ]);
}

public function test_mark_as_paid_rejects_invalid_date(): void
{
    Sanctum::actingAs($this->finance);

    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
        'payment_date' => 'not-a-date',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);
}

public function test_mark_as_paid_rejects_future_payment_date(): void
{
    Sanctum::actingAs($this->finance);

    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $futureDate = now()->addDays(7)->format('Y-m-d');
    $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
        'payment_date' => $futureDate,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['payment_date']);

    $this->assertDatabaseHas('payrolls', [
        'id' => $payroll->id,
        'status' => 'approved',
    ]);
}
```

- [ ] **Step 2: Verify RED → currently passes (inline rule already enforces)**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=mark_as_paid
```

- [ ] **Step 3: Create FormRequest**

`team-sync-be/app/Http/Requests/Payroll/PayrollMarkAsPaidRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollMarkAsPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
```

- [ ] **Step 4: Swap signature**

```php
public function markAsPaid(PayrollMarkAsPaidRequest $request, string $id): JsonResponse
```

- [ ] **Step 5: Verify GREEN + regression — high-risk method**

```bash
php artisan test --filter=Payroll
```

Existing reconciliation tests must still pass.

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollMarkAsPaidRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "feat: extract PayrollMarkAsPaidRequest"
```

### Task 2.3: Create `PayrollAnalyticsRequest`

**Files:**
- Create: `team-sync-be/app/Http/Requests/Payroll/PayrollAnalyticsRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 494-500
- Test: append

- [ ] **Step 1: Append failing tests**

```php
public function test_get_analytics_rejects_months_below_1(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/analytics?months=0')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['months']);
}

public function test_get_analytics_rejects_months_above_24(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/analytics?months=25')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['months']);
}

public function test_get_analytics_rejects_non_integer_months(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/analytics?months=six')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['months']);
}

public function test_get_analytics_rejects_float_months(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/analytics?months=3.5')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['months']);
}
```

- [ ] **Step 2: Verify RED → GREEN**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=get_analytics
```

- [ ] **Step 3: Create FormRequest**

`team-sync-be/app/Http/Requests/Payroll/PayrollAnalyticsRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }
}
```

- [ ] **Step 4: Swap signature**

```php
public function getAnalytics(PayrollAnalyticsRequest $request): JsonResponse
```

- [ ] **Step 5: Verify GREEN + regression**

```bash
php artisan test --filter=Payroll
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollAnalyticsRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "feat: extract PayrollAnalyticsRequest"
```

### Task 2.4: Pint + Full Suite Checkpoint

- [ ] `./vendor/bin/pint`
- [ ] `composer test 2>&1 | tail -3`
- [ ] Confirm pass count ≥ baseline.
- [ ] `git commit -am "chore: pint formatting after batch 2"` if needed.

---

## Batch 3 — Patch Existing FormRequests

### Task 3.1: Patch `PayrollUpdateDetailRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Requests/Payroll/PayrollUpdateDetailRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 299-307
- Test: append validation tests + audit existing tests for decimal `final_salary`

- [ ] **Step 1: Audit existing tests for decimal payloads**

```bash
grep -rn "final_salary" team-sync-be/tests/Feature/Payroll/ | grep -E "[0-9]+\.[0-9]"
```

Expected: zero hits (IDR is integer-only). If hits found, those tests are wrong — fix them in Step 3.

- [ ] **Step 2: Append failing validation tests**

```php
public function test_update_detail_rejects_negative_final_salary(): void
{
    Sanctum::actingAs($this->finance);

    $detail = \App\Models\PayrollDetail::factory()->create();

    $this->putJson("/api/v1/payroll-details/{$detail->id}", [
        'final_salary' => -1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['final_salary']);
}

public function test_update_detail_rejects_float_final_salary(): void
{
    Sanctum::actingAs($this->finance);

    $detail = \App\Models\PayrollDetail::factory()->create();

    $this->putJson("/api/v1/payroll-details/{$detail->id}", [
        'final_salary' => 9200000.50,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['final_salary']);
}

public function test_update_detail_rejects_non_integer_final_salary(): void
{
    Sanctum::actingAs($this->finance);

    $detail = \App\Models\PayrollDetail::factory()->create();

    $this->putJson("/api/v1/payroll-details/{$detail->id}", [
        'final_salary' => '10juta',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['final_salary']);
}
```

- [ ] **Step 3: Verify RED — float test should FAIL**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=update_detail
```

Expected: float test fails because current FormRequest uses `numeric` (allows decimals).

- [ ] **Step 4: Patch FormRequest**

In `team-sync-be/app/Http/Requests/Payroll/PayrollUpdateDetailRequest.php`:

Before:
```php
public function rules(): array
{
    return [
        'notes' => ['nullable', 'string'],
        'final_salary' => ['nullable', 'numeric', 'min:0'],
    ];
}
```

After:
```php
public function rules(): array
{
    return [
        'notes' => ['nullable', 'string'],
        'final_salary' => ['nullable', 'integer', 'min:0'],
        'updated_at' => ['nullable', 'date_format:Y-m-d H:i:s'],
    ];
}
```

> **Note:** Inline rule at controller line 304 currently uses `nullable|string` for `updated_at`. The FormRequest tightens this to `date_format:Y-m-d H:i:s` to guarantee optimistic-lock comparisons receive a parseable timestamp. This is a deliberate tightening — see "Goal" section.

- [ ] **Step 5: Swap controller signature**

```php
public function updateDetail(PayrollUpdateDetailRequest $request, string $id): JsonResponse
```

Replace inline `validate()` removal.

- [ ] **Step 6: Verify GREEN + regression**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
php artisan test --filter=Payroll
```

If `PayrollDetailUpdateTest` breaks due to decimal test data, fix the test data (use integers).

- [ ] **Step 7: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollUpdateDetailRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "fix: tighten PayrollUpdateDetailRequest to integer salary + updated_at"
```

### Task 3.2: Patch `PayrollReopenRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Requests/Payroll/PayrollReopenRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 396-402
- Test: append

- [ ] **Step 1: Append failing tests**

```php
public function test_reopen_payroll_requires_reason(): void
{
    Sanctum::actingAs($this->finance);

    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
}

public function test_reopen_payroll_rejects_reason_under_10_chars(): void
{
    Sanctum::actingAs($this->finance);

    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
        'reason' => 'short',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);
}

public function test_reopen_payroll_rejects_reason_over_500_chars(): void
{
    Sanctum::actingAs($this->finance);

    $payroll = \App\Models\Payroll::factory()->create(['status' => 'approved']);

    $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
        'reason' => str_repeat('a', 501),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reason']);

    $this->assertDatabaseHas('payrolls', [
        'id' => $payroll->id,
        'status' => 'approved',
    ]);
}
```

- [ ] **Step 2: Verify RED — `over_500_chars` test should fail**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=reopen_payroll
```

Expected: `over_500_chars` test fails because current FormRequest has no `max:500`.

- [ ] **Step 3: Patch FormRequest**

In `team-sync-be/app/Http/Requests/Payroll/PayrollReopenRequest.php`:

Before:
```php
'reason' => ['required', 'string', 'min:10'],
```

After:
```php
'reason' => ['required', 'string', 'min:10', 'max:500'],
```

- [ ] **Step 4: Swap controller signature**

```php
public function reopenPayroll(PayrollReopenRequest $request, string $id): JsonResponse
```

- [ ] **Step 5: Verify GREEN + regression**

```bash
php artisan test --filter=Payroll
```

- [ ] **Step 6: Commit**

```bash
git commit -am "fix: add max:500 to PayrollReopenRequest reason rule"
```

### Task 3.3: Pint + Checkpoint

- [ ] `./vendor/bin/pint`
- [ ] `composer test 2>&1 | tail -3`
- [ ] Commit if Pint changes anything.

---

## Batch 4 — Rewrite Mismatched FormRequests

### Task 4.1: Add `getComparison` Coverage Before Rewrite

**Files:**
- Create: `team-sync-be/tests/Feature/Payroll/PayrollComparisonTest.php`

`getComparison` currently has zero tests. Lock baseline before changing FormRequest.

- [ ] **Step 1: Create happy-path + validation tests**

```php
<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollComparisonTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'payroll-statistics', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('payroll-statistics');

        $this->finance = User::factory()->create();
        $this->finance->assignRole('Finance');
    }

    public function test_comparison_returns_valid_structure_for_two_months(): void
    {
        Sanctum::actingAs($this->finance);

        Payroll::factory()->create(['salary_month' => '2026-03-01', 'status' => 'paid']);
        Payroll::factory()->create(['salary_month' => '2026-04-01', 'status' => 'paid']);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-03&month2=2026-04')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    }

    public function test_comparison_requires_month1(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month2=2026-04')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month1']);
    }

    public function test_comparison_requires_month2(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-03')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month2']);
    }

    public function test_comparison_rejects_invalid_month_format(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=April-2026&month2=2026-04')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month1']);
    }

    public function test_comparison_handles_months_with_no_data_gracefully(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2099-01&month2=2099-02')
            ->assertSuccessful();
    }
}
```

- [ ] **Step 2: Verify all pass against current inline implementation**

```bash
php artisan test tests/Feature/Payroll/PayrollComparisonTest.php
```

Expected: all pass. This is the baseline before rewrite.

- [ ] **Step 3: Commit baseline coverage**

```bash
git add team-sync-be/tests/Feature/Payroll/PayrollComparisonTest.php
git commit -m "test: add baseline coverage for payroll comparison endpoint"
```

### Task 4.2: Rewrite `PayrollComparisonRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Requests/Payroll/PayrollComparisonRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 514-520

- [ ] **Step 1: Confirm no other consumers**

```bash
grep -rn "PayrollComparisonRequest" team-sync-be/app/ team-sync-be/tests/
```

Expected: only the controller (will reference) and the class file itself. No other code uses the old `months[]` shape.

- [ ] **Step 2: Replace FormRequest contents**

`team-sync-be/app/Http/Requests/Payroll/PayrollComparisonRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month1' => ['required', 'date_format:Y-m'],
            'month2' => ['required', 'date_format:Y-m'],
        ];
    }
}
```

> **Note:** Plan does NOT add a `different:month1` rule. Inline rules at controller line 516–519 only enforce `required|date_format:Y-m` for both fields. Adding new constraints in this batch would smuggle behavior change into a mechanical rewrite. If "same-month comparison" is a real concern, address in a separate PR.

- [ ] **Step 3: Swap controller signature**

```php
public function getComparison(PayrollComparisonRequest $request): JsonResponse
```

Add import.

- [ ] **Step 4: Verify GREEN — comparison tests + payroll suite**

```bash
php artisan test tests/Feature/Payroll/PayrollComparisonTest.php
php artisan test --filter=Payroll
```

- [ ] **Step 5: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollComparisonRequest.php team-sync-be/app/Http/Controllers/PayrollController.php
git commit -m "refactor: rewrite PayrollComparisonRequest to match flat month1/month2 shape"
```

### Task 4.3: Rewrite `PayrollExportReportRequest`

**Files:**
- Modify: `team-sync-be/app/Http/Requests/Payroll/PayrollExportReportRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 659-669
- Test: append validation tests

- [ ] **Step 1: Append failing tests**

```php
public function test_export_report_requires_status(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/export-report?period_type=monthly&month=2026-04')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
}

public function test_export_report_rejects_invalid_status(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/export-report?status=draft&period_type=monthly&month=2026-04')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
}

public function test_export_report_requires_month_when_period_type_monthly(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/export-report?status=paid&period_type=monthly')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['month']);
}

public function test_export_report_requires_year_when_period_type_yearly(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/export-report?status=paid&period_type=yearly')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['year']);
}

public function test_export_report_rejects_non_4_digit_year(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls/export-report?status=paid&period_type=yearly&year=26')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['year']);
}
```

- [ ] **Step 2: Verify RED**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=export_report
```

Expected: most fail because current `PayrollExportReportRequest` has wrong field set.

- [ ] **Step 3: Replace FormRequest contents**

`team-sync-be/app/Http/Requests/Payroll/PayrollExportReportRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,paid,all'],
            'period_type' => ['required', 'in:monthly,yearly'],
            'report_type' => ['nullable', 'in:summary,detail'],
            'month' => ['required_if:period_type,monthly', 'nullable', 'date_format:Y-m'],
            'year' => ['required_if:period_type,yearly', 'nullable', 'digits:4'],
        ];
    }
}
```

- [ ] **Step 4: Swap controller signature**

```php
public function exportReport(PayrollExportReportRequest $request): JsonResponse
```

- [ ] **Step 5: Verify GREEN + regression — high-risk method**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php
php artisan test --filter=PayrollReportExport
php artisan test --filter=Payroll
```

If `PayrollReportExportTest` breaks, root-cause: the existing tests likely send the same field shape, so they should pass. If they fail, check field name mismatches (e.g., `type` vs `report_type`).

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollExportReportRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "refactor: rewrite PayrollExportReportRequest with required_if rules"
```

### Task 4.4: Pint + Checkpoint

- [ ] `./vendor/bin/pint`
- [ ] `composer test 2>&1 | tail -3`
- [ ] Commit if needed.

---

## Batch 5 — Cover `index` Method

### Task 5.1: Create `PayrollIndexRequest`

`index` (line 94) currently uses `$request->search` and `$request->limit` without validation. Add a FormRequest to lock the contract.

**Files:**
- Create: `team-sync-be/app/Http/Requests/Payroll/PayrollIndexRequest.php`
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php` lines 94-109
- Test: append

- [ ] **Step 0: FE pre-flight — confirm caller doesn't send `limit > 100`**

```bash
grep -rn "/api/v1/payrolls" team-sync-fe/src/stores/ team-sync-fe/src/composables/
grep -rn "limit" team-sync-fe/src/stores/payroll*.js team-sync-fe/src/stores/payslip*.js
```

If FE sends `limit > 100` anywhere, raise `max:100` in the FormRequest rule below to match, OR coordinate with FE to clamp. This batch adds validation where none existed — without this check, FE calls could 422 silently.

- [ ] **Step 1: Append failing tests**

```php
public function test_payroll_index_rejects_non_integer_limit(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls?limit=abc')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);
}

public function test_payroll_index_rejects_limit_above_100(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls?limit=200')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);
}

public function test_payroll_index_rejects_limit_zero(): void
{
    Sanctum::actingAs($this->finance);

    $this->getJson('/api/v1/payrolls?limit=0')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);
}
```

- [ ] **Step 2: Verify RED — currently passes (no validation)**

```bash
php artisan test tests/Feature/Payroll/PayrollControllerValidationTest.php --filter=payroll_index
```

Expected: FAIL (current `index` returns 200 because no validation).

- [ ] **Step 3: Create FormRequest**

`team-sync-be/app/Http/Requests/Payroll/PayrollIndexRequest.php`:

```php
<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
```

- [ ] **Step 4: Swap controller signature**

```php
public function index(PayrollIndexRequest $request): JsonResponse
```

- [ ] **Step 5: Verify GREEN + regression**

```bash
php artisan test --filter=Payroll
```

- [ ] **Step 6: Commit**

```bash
git add team-sync-be/app/Http/Requests/Payroll/PayrollIndexRequest.php team-sync-be/app/Http/Controllers/PayrollController.php team-sync-be/tests/Feature/Payroll/PayrollControllerValidationTest.php
git commit -m "feat: add PayrollIndexRequest for search/limit validation"
```

### Task 5.2: Final Cleanup — Remove Unused `Request` Import

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php`

- [ ] **Step 1: Audit remaining `Request` usage**

```bash
grep -n "Request " team-sync-be/app/Http/Controllers/PayrollController.php
```

Methods that still need `Illuminate\Http\Request`:
- `approvePayroll(Request $request, ...)` — only uses `$request->user()`
- `resendNotifications(Request $request, ...)` — only uses `$request->user()`

Both legitimate uses. Keep `use Illuminate\Http\Request;` import.

- [ ] **Step 2: Verify no inline `validate()` remains**

```bash
grep -n '\$request->validate' team-sync-be/app/Http/Controllers/PayrollController.php
```

Expected: zero hits.

- [ ] **Step 3: Pint final pass**

```bash
./vendor/bin/pint
```

- [ ] **Step 4: Full BE suite**

```bash
composer test 2>&1 | tail -3
```

Expected: pass count ≥ Task 0 baseline. Zero failures.

- [ ] **Step 5: Commit Pint if any**

```bash
git status --short
git commit -am "chore: pint formatting after batch 5" || true
```

---

## Batch 6 — Plan Doc + PR

### Task 6.1: Update Plan Document

**Files:**
- Modify: `docs/plans/on_going/2026-05-18-implementation-gap-closure.md` (Task 2 section)

- [ ] **Step 1: Mark Task 2 complete in umbrella plan**

In `docs/plans/on_going/2026-05-18-implementation-gap-closure.md`, update Task 2 heading to `### Task 2: Extract Payroll FormRequests First — COMPLETED 2026-05-18` and add an Implementation notes block summarizing batches.

- [ ] **Step 2: Commit**

```bash
git commit -am "docs: mark Task 2 complete with payroll FormRequest notes"
```

### Task 6.2: Create PR

- [ ] **Step 1: Push branch**

```bash
git push -u origin chore/payroll-form-requests
```

- [ ] **Step 2: Create PR via gh CLI**

```bash
gh pr create --base main --head chore/payroll-form-requests \
  --title "chore: extract payroll inline validation to FormRequests" \
  --body "$(cat <<'EOF'
## Summary
- Replace 14 inline \`\$request->validate()\` calls in PayrollController with typed FormRequest injection.
- Reuse 6 existing FormRequests as-is, patch 2 (PayrollUpdateDetailRequest, PayrollReopenRequest), rewrite 2 (PayrollComparisonRequest, PayrollExportReportRequest), add 4 new (PayrollGenerateReadinessRequest, PayrollMarkAsPaidRequest, PayrollAnalyticsRequest, PayrollIndexRequest).
- Add ~45 validation-failure tests in new PayrollControllerValidationTest, plus baseline coverage for previously untested getComparison endpoint.

## Test Plan
- composer test
- ./vendor/bin/pint
- bun run test (FE — verify 422 contract unchanged)

## Notes
- No PayrollService extraction (Task 3, separate PR).
- No Resource shape changes.
- ResolveReconciliationExceptionRequest stays in root namespace (out-of-scope rename).
- final_salary now strictly integer (matches IDR domain rule, was numeric).
EOF
)"
```

- [ ] **Step 3: Wait for CI green**

```bash
gh pr checks <PR_NUMBER>
```

- [ ] **Step 4: Reply to any Copilot review comments per receiving-code-review skill**

---

## Verification Checklist (Definition of Done)

- [ ] `grep -c '\$request->validate' team-sync-be/app/Http/Controllers/PayrollController.php` returns `0`
- [ ] `composer test` passes with pass count ≥ Task 0 baseline
- [ ] `./vendor/bin/pint` returns `{"result":"pass"}`
- [ ] `bun run test` in `team-sync-fe` still passes (contract preserved)
- [ ] No files outside `team-sync-be/app/Http/Controllers/PayrollController.php`, `team-sync-be/app/Http/Requests/Payroll/**`, `team-sync-be/app/Http/Requests/ResolveReconciliationExceptionRequest.php` (untouched), and `team-sync-be/tests/Feature/Payroll/**` modified (excluding plan docs)
- [ ] Every FormRequest has `authorize(): bool { return true; }` (project convention, route handles permission)
- [ ] Every FormRequest uses array-syntax rules (project convention)
- [ ] Every new/rewritten FormRequest has at least one validation-failure test
- [ ] `getComparison` has happy-path coverage (was untested before)
- [ ] Plan document updated with implementation notes

---

## Risks + Mitigations Recap

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|-----------|
| `$validated` references break after `$request->validate()` removal | High | High — every method 500s | Follow `$validated = $request->validated();` template at top of plan; pre-flight grep before each task |
| Rewritten FormRequest rejects previously-valid input | High | Medium | TDD per method; diff inline rules vs FormRequest rules character-by-character; full suite after each batch |
| `numeric`→`integer` breaks decimal test fixtures | Medium | Low | Pre-audit grep; fix test data (IDR is integer-only per domain rule) |
| `authorize(): true` bypasses intended check | Low | High | Permission gating lives in `PayrollController::middleware()` lines 79–89 (Spatie `PermissionMiddleware::using()`), NOT in `routes/api.php`. Verify each method's permission group there before extraction |
| New `before_or_equal:today` on `payment_date` rejects existing fixtures | Medium | Low | Pre-audit grep `payment_date` in payroll tests; if any fixture passes a future date, fix test data or use `Carbon::setTestNow()` |
| Tightened `updated_at: date_format:Y-m-d H:i:s` rejects FE-sent ISO-8601 strings | Medium | Medium | Inspect `team-sync-fe/src/stores/payroll*.js` for `updated_at` payload format before extraction; if FE sends `Y-m-d\TH:i:s.000000Z`, relax rule to `nullable\|date` |
| Implicit dependency on inline error message format | Low | Low | Laravel returns identical 422 shape for FormRequest; project has no `messages()` overrides |
| Scope creep into PayrollService | Medium | High | Hard guardrail: if behavior change needed, STOP and note for Task 3 |

---

## Out-of-Scope (Explicit Exclusions)

- PayrollService extraction (Task 3 of umbrella plan)
- Renaming `ResolveReconciliationExceptionRequest` namespace
- Changing JsonResource shapes
- Adding `prepareForValidation()` or `messages()` (no project precedent in Payroll/)
- Moving repository code, refactoring controller method bodies beyond signature + `$validated` source swap
- Touching `PayrollRepository` or `PayrollRepositoryInterface`
- FE store changes (contract preserved — FE untouched)
- Per-record ownership / tenant scoping in FormRequest. Current architecture is single-tenant; Finance role has company-wide payroll access by design. Multi-tenant scoping is a separate concern (model global scope), not FormRequest-level.
- Adding `different:month1` to `PayrollComparisonRequest` (not in inline rules; would smuggle behavior change)
- Adding new tightening rules beyond those explicitly listed in the Goal section
