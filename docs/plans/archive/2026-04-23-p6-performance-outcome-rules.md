# P6 — Performance Outcome Rules Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Auto-map performance review outcomes (bonus, salary increase %, promotion eligibility, PIP) based on configurable rules tied to final_rating ranges, triggered automatically when HR calibrates/finalizes a review.

**Architecture:** New `performance_outcome_rules` table stores configurable rating-range → outcome mappings. New columns on `performance_reviews` store the applied outcome per review. A `PerformanceOutcomeService` auto-applies the matching rule when calibration completes. CRUD API for HR to manage rules. Frontend settings page + outcome display in ReviewDetail.

**Tech Stack:** Laravel 10 (migrations, Eloquent, Form Requests, API Resources), Vue 3 Composition API (`<script setup>`), Pinia Options API stores, Tailwind CSS.

---

## Context Summary

### Existing Data Model
- `performance_reviews`: has `final_rating` (decimal 3,2), `final_rating_label`, `status` enum, `calibrated_at`
- `PerformanceRatingHelper::RATING_LABELS`: Outstanding (≥4.50), Exceeds (≥3.50), Meets (≥2.50), Needs Improvement (≥1.50), Unsatisfactory (<1.50)
- Calibration flow: `PerformanceReviewRepository::calibrateReview()` sets status=completed, final_rating, calibrated_at
- `PerformanceReviewController::calibrateReview()` calls repository then returns response
- Routes: `POST /api/v1/performance/reviews/{id}/calibrate` (permission: `review-calibrate`)

### Existing Patterns
- Models: `protected $fillable`, `protected $casts`, relationships via methods
- Controllers: constructor-injected repository interface, `ResponseHelper::jsonResponse()`
- Routes: `routes/api.php` → `Route::prefix('performance')` group with `PermissionMiddleware`
- Frontend store: Pinia Options API (`defineStore` with `state/actions`), `axiosInstance`, `handleError`
- Frontend views: `<script setup>`, Tailwind, lucide-vue-next icons, MainCard component

### Rating Scale Reference
| Rating Range | Label | Typical Outcome |
|---|---|---|
| ≥ 4.50 | Outstanding | 3 months bonus, 10% salary increase, promotion eligible |
| 3.50 – 4.49 | Exceeds Expectations | 2 months bonus, 7% salary increase |
| 2.50 – 3.49 | Meets Expectations | 1 month bonus, 4% salary increase |
| 1.50 – 2.49 | Needs Improvement | No bonus, no increase, PIP required |
| < 1.50 | Unsatisfactory | No bonus, no increase, PIP required |

---

## Task 1: Migration — `performance_outcome_rules` table

**Files:**
- Create: `team-sync-be/database/migrations/2026_04_23_100000_create_performance_outcome_rules_table.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_outcome_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label');                          // e.g. "Outstanding"
            $table->decimal('min_rating', 3, 2);              // inclusive lower bound
            $table->decimal('max_rating', 3, 2);              // inclusive upper bound
            $table->decimal('bonus_months', 4, 2)->default(0);         // e.g. 3.00
            $table->decimal('salary_increase_pct', 5, 2)->default(0);  // e.g. 10.00 (%)
            $table->boolean('promotion_eligible')->default(false);
            $table->boolean('pip_required')->default(false);   // Performance Improvement Plan
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['min_rating', 'max_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_outcome_rules');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: Table created successfully

**Step 3: Commit**

```bash
git add database/migrations/2026_04_23_100000_create_performance_outcome_rules_table.php
git commit -m "feat(P6): add performance_outcome_rules migration"
```

---

## Task 2: Migration — Add outcome fields to `performance_reviews`

**Files:**
- Create: `team-sync-be/database/migrations/2026_04_23_100001_add_outcome_fields_to_performance_reviews_table.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->foreignId('outcome_rule_id')->nullable()->after('acknowledged_by_employee_at')
                ->constrained('performance_outcome_rules')->nullOnDelete();
            $table->decimal('bonus_months', 4, 2)->nullable()->after('outcome_rule_id');
            $table->decimal('salary_increase_pct', 5, 2)->nullable()->after('bonus_months');
            $table->boolean('promotion_eligible')->nullable()->after('salary_increase_pct');
            $table->boolean('pip_required')->nullable()->after('promotion_eligible');
            $table->timestamp('outcome_applied_at')->nullable()->after('pip_required');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outcome_rule_id');
            $table->dropColumn([
                'bonus_months',
                'salary_increase_pct',
                'promotion_eligible',
                'pip_required',
                'outcome_applied_at',
            ]);
        });
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate`
Expected: Columns added successfully

**Step 3: Commit**

```bash
git add database/migrations/2026_04_23_100001_add_outcome_fields_to_performance_reviews_table.php
git commit -m "feat(P6): add outcome fields to performance_reviews"
```

---

## Task 3: Model `PerformanceOutcomeRule` + Update `PerformanceReview` model

**Files:**
- Create: `team-sync-be/app/Models/PerformanceOutcomeRule.php`
- Modify: `team-sync-be/app/Models/PerformanceReview.php`

**Step 1: Create PerformanceOutcomeRule model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceOutcomeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'min_rating',
        'max_rating',
        'bonus_months',
        'salary_increase_pct',
        'promotion_eligible',
        'pip_required',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_rating' => 'decimal:2',
        'max_rating' => 'decimal:2',
        'bonus_months' => 'decimal:2',
        'salary_increase_pct' => 'decimal:2',
        'promotion_eligible' => 'boolean',
        'pip_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Find the matching active rule for a given final_rating.
     */
    public static function findForRating(float $rating): ?self
    {
        return static::where('is_active', true)
            ->where('min_rating', '<=', $rating)
            ->where('max_rating', '>=', $rating)
            ->orderByDesc('min_rating')
            ->first();
    }
}
```

**Step 2: Update PerformanceReview model — add new fillable fields + relationship**

Add to `$fillable` array:
```php
'outcome_rule_id',
'bonus_months',
'salary_increase_pct',
'promotion_eligible',
'pip_required',
'outcome_applied_at',
```

Add to `$casts` array:
```php
'bonus_months' => 'decimal:2',
'salary_increase_pct' => 'decimal:2',
'promotion_eligible' => 'boolean',
'pip_required' => 'boolean',
'outcome_applied_at' => 'datetime',
```

Add relationship method:
```php
public function outcomeRule(): BelongsTo
{
    return $this->belongsTo(PerformanceOutcomeRule::class, 'outcome_rule_id');
}
```

**Step 3: Commit**

```bash
git add app/Models/PerformanceOutcomeRule.php app/Models/PerformanceReview.php
git commit -m "feat(P6): add PerformanceOutcomeRule model and update PerformanceReview"
```

---

## Task 4: Seeder — Default outcome rules

**Files:**
- Create: `team-sync-be/database/seeders/PerformanceOutcomeRuleSeeder.php`
- Modify: `team-sync-be/database/seeders/DatabaseSeeder.php` (add call)

**Step 1: Create seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\PerformanceOutcomeRule;
use Illuminate\Database\Seeder;

class PerformanceOutcomeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'label' => 'Outstanding',
                'min_rating' => 4.50,
                'max_rating' => 5.00,
                'bonus_months' => 3.00,
                'salary_increase_pct' => 10.00,
                'promotion_eligible' => true,
                'pip_required' => false,
                'description' => 'Top performer — eligible for promotion and maximum bonus.',
            ],
            [
                'label' => 'Exceeds Expectations',
                'min_rating' => 3.50,
                'max_rating' => 4.49,
                'bonus_months' => 2.00,
                'salary_increase_pct' => 7.00,
                'promotion_eligible' => false,
                'pip_required' => false,
                'description' => 'Above average performer — eligible for bonus and salary increase.',
            ],
            [
                'label' => 'Meets Expectations',
                'min_rating' => 2.50,
                'max_rating' => 3.49,
                'bonus_months' => 1.00,
                'salary_increase_pct' => 4.00,
                'promotion_eligible' => false,
                'pip_required' => false,
                'description' => 'Satisfactory performer — standard bonus and salary increase.',
            ],
            [
                'label' => 'Needs Improvement',
                'min_rating' => 1.50,
                'max_rating' => 2.49,
                'bonus_months' => 0.00,
                'salary_increase_pct' => 0.00,
                'promotion_eligible' => false,
                'pip_required' => true,
                'description' => 'Below expectations — Performance Improvement Plan required.',
            ],
            [
                'label' => 'Unsatisfactory',
                'min_rating' => 1.00,
                'max_rating' => 1.49,
                'bonus_months' => 0.00,
                'salary_increase_pct' => 0.00,
                'promotion_eligible' => false,
                'pip_required' => true,
                'description' => 'Significantly below expectations — immediate PIP required.',
            ],
        ];

        foreach ($rules as $rule) {
            PerformanceOutcomeRule::updateOrCreate(
                ['label' => $rule['label']],
                $rule
            );
        }
    }
}
```

**Step 2: Add to DatabaseSeeder**

Add `$this->call(PerformanceOutcomeRuleSeeder::class);` in the `run()` method.

**Step 3: Run seeder**

Run: `php artisan db:seed --class=PerformanceOutcomeRuleSeeder`
Expected: 5 rules created

**Step 4: Commit**

```bash
git add database/seeders/PerformanceOutcomeRuleSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(P6): add default performance outcome rules seeder"
```

---

## Task 5: Service — `PerformanceOutcomeService`

**Files:**
- Create: `team-sync-be/app/Services/Performance/PerformanceOutcomeService.php`

**Step 1: Write the failing test**

Create: `team-sync-be/tests/Unit/Services/PerformanceOutcomeServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Models\PerformanceOutcomeRule;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceOutcomeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceOutcomeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceOutcomeService();
        $this->seed(\Database\Seeders\PerformanceOutcomeRuleSeeder::class);
    }

    public function test_applies_outstanding_outcome_for_high_rating(): void
    {
        $review = PerformanceReview::factory()->create([
            'final_rating' => 4.75,
            'status' => 'completed',
        ]);

        $result = $this->service->applyOutcome($review);

        $this->assertNotNull($result);
        $this->assertEquals(3.00, $result->bonus_months);
        $this->assertEquals(10.00, $result->salary_increase_pct);
        $this->assertTrue($result->promotion_eligible);
        $this->assertFalse($result->pip_required);
        $this->assertNotNull($result->outcome_applied_at);
        $this->assertNotNull($result->outcome_rule_id);
    }

    public function test_applies_pip_for_low_rating(): void
    {
        $review = PerformanceReview::factory()->create([
            'final_rating' => 1.80,
            'status' => 'completed',
        ]);

        $result = $this->service->applyOutcome($review);

        $this->assertNotNull($result);
        $this->assertEquals(0.00, $result->bonus_months);
        $this->assertEquals(0.00, $result->salary_increase_pct);
        $this->assertFalse($result->promotion_eligible);
        $this->assertTrue($result->pip_required);
    }

    public function test_returns_null_when_no_final_rating(): void
    {
        $review = PerformanceReview::factory()->create([
            'final_rating' => null,
            'status' => 'completed',
        ]);

        $result = $this->service->applyOutcome($review);

        $this->assertNull($result->outcome_rule_id);
    }

    public function test_returns_null_when_no_matching_rule(): void
    {
        // Deactivate all rules
        PerformanceOutcomeRule::query()->update(['is_active' => false]);

        $review = PerformanceReview::factory()->create([
            'final_rating' => 4.00,
            'status' => 'completed',
        ]);

        $result = $this->service->applyOutcome($review);

        $this->assertNull($result->outcome_rule_id);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PerformanceOutcomeServiceTest`
Expected: FAIL (class not found)

**Step 3: Create the service**

```php
<?php

namespace App\Services\Performance;

use App\Models\PerformanceOutcomeRule;
use App\Models\PerformanceReview;

class PerformanceOutcomeService
{
    /**
     * Apply the matching outcome rule to a completed review.
     *
     * Looks up the active PerformanceOutcomeRule whose rating range
     * contains the review's final_rating, then stamps the outcome
     * fields onto the review.
     *
     * @return PerformanceReview The updated review (saved to DB)
     */
    public function applyOutcome(PerformanceReview $review): PerformanceReview
    {
        if ($review->final_rating === null) {
            return $review;
        }

        $rule = PerformanceOutcomeRule::findForRating((float) $review->final_rating);

        if (!$rule) {
            return $review;
        }

        $review->update([
            'outcome_rule_id' => $rule->id,
            'bonus_months' => $rule->bonus_months,
            'salary_increase_pct' => $rule->salary_increase_pct,
            'promotion_eligible' => $rule->promotion_eligible,
            'pip_required' => $rule->pip_required,
            'outcome_applied_at' => now(),
        ]);

        return $review->fresh();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PerformanceOutcomeServiceTest`
Expected: PASS (all 4 tests)

> **Note:** If PerformanceReview factory doesn't exist yet, create it first at `database/factories/PerformanceReviewFactory.php` with minimal required fields (cycle_id, staff_member_id, status). You'll need a PerformanceReviewCycle factory and StaffMemberProfile factory too — check if they exist first.

**Step 5: Commit**

```bash
git add app/Services/Performance/PerformanceOutcomeService.php tests/Unit/Services/PerformanceOutcomeServiceTest.php
git commit -m "feat(P6): add PerformanceOutcomeService with tests"
```

---

## Task 6: Integrate outcome into calibration finalize flow

**Files:**
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php` (method `calibrateReview`)

**Step 1: Write the failing test**

Create: `team-sync-be/tests/Feature/Performance/OutcomeIntegrationTest.php`

```php
<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutcomeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calibrating_review_auto_applies_outcome(): void
    {
        $this->seed(\Database\Seeders\PerformanceOutcomeRuleSeeder::class);

        // Setup: create HR user, cycle, review in pending_calibration with manager ratings
        // (Use existing test patterns from the codebase)
        $hr = User::factory()->create();
        $hr->assignRole('hr');

        // Create review at pending_calibration with existing manager ratings
        // ... (adapt from existing calibration tests)

        $response = $this->actingAs($hr)
            ->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
                'responses' => [],  // Accept manager ratings as-is
            ]);

        $response->assertOk();

        $review->refresh();
        $this->assertEquals('completed', $review->status);
        $this->assertNotNull($review->outcome_rule_id);
        $this->assertNotNull($review->outcome_applied_at);
        $this->assertNotNull($review->bonus_months);
    }
}
```

**Step 2: Modify `calibrateReview` in repository**

In `PerformanceReviewRepository::calibrateReview()`, after the existing `$review->update([...])` block (around line 182-189), add:

```php
// Auto-apply performance outcome rule
$outcomeService = app(\App\Services\Performance\PerformanceOutcomeService::class);
$review = $outcomeService->applyOutcome($review);
```

**Step 3: Run test**

Run: `php artisan test --filter=OutcomeIntegrationTest`
Expected: PASS

**Step 4: Commit**

```bash
git add app/Repositories/PerformanceReviewRepository.php tests/Feature/Performance/OutcomeIntegrationTest.php
git commit -m "feat(P6): auto-apply outcome on calibration finalize"
```

---

## Task 7: API Resource for outcome rules

**Files:**
- Create: `team-sync-be/app/Http/Resources/PerformanceOutcomeRuleResource.php`

**Step 1: Create resource**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceOutcomeRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'min_rating' => (float) $this->min_rating,
            'max_rating' => (float) $this->max_rating,
            'bonus_months' => (float) $this->bonus_months,
            'salary_increase_pct' => (float) $this->salary_increase_pct,
            'promotion_eligible' => $this->promotion_eligible,
            'pip_required' => $this->pip_required,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Resources/PerformanceOutcomeRuleResource.php
git commit -m "feat(P6): add PerformanceOutcomeRuleResource"
```

---

## Task 8: Form Requests for outcome rules CRUD

**Files:**
- Create: `team-sync-be/app/Http/Requests/Performance/StoreOutcomeRuleRequest.php`
- Create: `team-sync-be/app/Http/Requests/Performance/UpdateOutcomeRuleRequest.php`

**Step 1: Create StoreOutcomeRuleRequest**

```php
<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutcomeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permission handled by middleware
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'min_rating' => ['required', 'numeric', 'min:1.00', 'max:5.00'],
            'max_rating' => ['required', 'numeric', 'min:1.00', 'max:5.00', 'gte:min_rating'],
            'bonus_months' => ['required', 'numeric', 'min:0'],
            'salary_increase_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'promotion_eligible' => ['required', 'boolean'],
            'pip_required' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

**Step 2: Create UpdateOutcomeRuleRequest** (same rules but all optional)

```php
<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutcomeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'min_rating' => ['sometimes', 'numeric', 'min:1.00', 'max:5.00'],
            'max_rating' => ['sometimes', 'numeric', 'min:1.00', 'max:5.00'],
            'bonus_months' => ['sometimes', 'numeric', 'min:0'],
            'salary_increase_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'promotion_eligible' => ['sometimes', 'boolean'],
            'pip_required' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Requests/Performance/StoreOutcomeRuleRequest.php app/Http/Requests/Performance/UpdateOutcomeRuleRequest.php
git commit -m "feat(P6): add form requests for outcome rules CRUD"
```

---

## Task 9: Controller — `PerformanceOutcomeRuleController`

**Files:**
- Create: `team-sync-be/app/Http/Controllers/PerformanceOutcomeRuleController.php`
- Modify: `team-sync-be/routes/api.php`

**Step 1: Create controller**

```php
<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\StoreOutcomeRuleRequest;
use App\Http\Requests\Performance\UpdateOutcomeRuleRequest;
use App\Http\Resources\PerformanceOutcomeRuleResource;
use App\Models\PerformanceOutcomeRule;

class PerformanceOutcomeRuleController extends Controller
{
    public function index()
    {
        $rules = PerformanceOutcomeRule::orderBy('min_rating')->get();
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rules retrieved successfully',
            PerformanceOutcomeRuleResource::collection($rules)
        );
    }

    public function store(StoreOutcomeRuleRequest $request)
    {
        $rule = PerformanceOutcomeRule::create($request->validated());
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule created successfully',
            new PerformanceOutcomeRuleResource($rule),
            201
        );
    }

    public function show(int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule retrieved successfully',
            new PerformanceOutcomeRuleResource($rule)
        );
    }

    public function update(UpdateOutcomeRuleRequest $request, int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        $rule->update($request->validated());
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule updated successfully',
            new PerformanceOutcomeRuleResource($rule->fresh())
        );
    }

    public function destroy(int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        $rule->delete();
        return ResponseHelper::jsonResponse(true, 'Outcome rule deleted successfully');
    }
}
```

**Step 2: Add routes**

In `routes/api.php`, inside the `Route::prefix('performance')` group, inside the `review-cycle-manage` middleware group (around line 210-214), add:

```php
// Outcome Rules (HR only — reuses review-cycle-manage permission)
Route::apiResource('outcome-rules', PerformanceOutcomeRuleController::class);
```

Don't forget to add the import at the top of `api.php`:
```php
use App\Http\Controllers\PerformanceOutcomeRuleController;
```

**Step 3: Write feature test**

Create: `team-sync-be/tests/Feature/Performance/OutcomeRuleCrudTest.php`

```php
<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceOutcomeRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutcomeRuleCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->hr = User::factory()->create();
        $this->hr->assignRole('hr');
    }

    public function test_hr_can_list_outcome_rules(): void
    {
        $this->seed(\Database\Seeders\PerformanceOutcomeRuleSeeder::class);

        $response = $this->actingAs($this->hr)
            ->getJson('/api/v1/performance/outcome-rules');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_hr_can_create_outcome_rule(): void
    {
        $response = $this->actingAs($this->hr)
            ->postJson('/api/v1/performance/outcome-rules', [
                'label' => 'Test Rule',
                'min_rating' => 3.00,
                'max_rating' => 4.00,
                'bonus_months' => 1.50,
                'salary_increase_pct' => 5.00,
                'promotion_eligible' => false,
                'pip_required' => false,
                'description' => 'Test description',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('performance_outcome_rules', ['label' => 'Test Rule']);
    }

    public function test_hr_can_update_outcome_rule(): void
    {
        $rule = PerformanceOutcomeRule::create([
            'label' => 'Original',
            'min_rating' => 1.00,
            'max_rating' => 2.00,
            'bonus_months' => 0,
            'salary_increase_pct' => 0,
            'promotion_eligible' => false,
            'pip_required' => false,
        ]);

        $response = $this->actingAs($this->hr)
            ->putJson("/api/v1/performance/outcome-rules/{$rule->id}", [
                'label' => 'Updated',
                'bonus_months' => 2.00,
            ]);

        $response->assertOk();
        $this->assertEquals('Updated', $rule->fresh()->label);
    }

    public function test_hr_can_delete_outcome_rule(): void
    {
        $rule = PerformanceOutcomeRule::create([
            'label' => 'To Delete',
            'min_rating' => 1.00,
            'max_rating' => 2.00,
            'bonus_months' => 0,
            'salary_increase_pct' => 0,
            'promotion_eligible' => false,
            'pip_required' => false,
        ]);

        $response = $this->actingAs($this->hr)
            ->deleteJson("/api/v1/performance/outcome-rules/{$rule->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('performance_outcome_rules', ['id' => $rule->id]);
    }
}
```

**Step 4: Run tests**

Run: `php artisan test --filter=OutcomeRuleCrudTest`
Expected: PASS (all 4 tests)

**Step 5: Commit**

```bash
git add app/Http/Controllers/PerformanceOutcomeRuleController.php routes/api.php tests/Feature/Performance/OutcomeRuleCrudTest.php
git commit -m "feat(P6): add outcome rules CRUD controller with tests"
```

---

## Task 10: Frontend — Pinia store actions for outcome rules

**Files:**
- Modify: `team-sync-fe/src/stores/performanceReview.js`

**Step 1: Add state**

In the `state` function, add:

```js
// Outcome Rules
outcomeRules: [],
outcomeRulesLoading: false,
```

**Step 2: Add actions**

```js
// Outcome Rules
async fetchOutcomeRules() {
    this.outcomeRulesLoading = true;
    this.error = null;
    try {
        const response = await axiosInstance.get('/performance/outcome-rules');
        this.outcomeRules = response.data.data;
        return response.data.data;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    } finally {
        this.outcomeRulesLoading = false;
    }
},

async createOutcomeRule(data) {
    this.error = null;
    try {
        const response = await axiosInstance.post('/performance/outcome-rules', data);
        this.outcomeRules.push(response.data.data);
        return response.data.data;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    }
},

async updateOutcomeRule(id, data) {
    this.error = null;
    try {
        const response = await axiosInstance.put(`/performance/outcome-rules/${id}`, data);
        const index = this.outcomeRules.findIndex(r => r.id === id);
        if (index !== -1) this.outcomeRules[index] = response.data.data;
        return response.data.data;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    }
},

async deleteOutcomeRule(id) {
    this.error = null;
    try {
        await axiosInstance.delete(`/performance/outcome-rules/${id}`);
        this.outcomeRules = this.outcomeRules.filter(r => r.id !== id);
        return true;
    } catch (error) {
        this.error = handleError(error);
        throw error;
    }
},
```

**Step 3: Commit**

```bash
git add team-sync-fe/src/stores/performanceReview.js
git commit -m "feat(P6): add outcome rules store actions"
```

---

## Task 11: Frontend — Outcome Rules Settings Page

**Files:**
- Create: `team-sync-fe/src/views/admin/performance/OutcomeRulesSettings.vue`
- Modify: `team-sync-fe/src/router/index.js` (add route)

**Step 1: Create the settings page**

A CRUD table for managing outcome rules. Uses MainCard, lucide-vue-next icons, Tailwind styling consistent with existing admin pages. Features:
- Table listing all rules (sorted by min_rating)
- Add/Edit modal with form fields
- Delete with confirmation
- Toggle active/inactive

> **Skill reference:** Use **vue-best-practices** and **frontend-design** skills when implementing this component.

Key fields in the form:
- Label (text)
- Min Rating / Max Rating (number, step 0.01, range 1.00-5.00)
- Bonus Months (number, step 0.5)
- Salary Increase % (number, step 0.5)
- Promotion Eligible (checkbox)
- PIP Required (checkbox)
- Description (textarea)
- Active (toggle)

**Step 2: Add route**

In the performance routes section of `router/index.js`, add:

```js
{
    path: '/admin/performance/outcome-rules',
    name: 'OutcomeRulesSettings',
    component: () => import('@/views/admin/performance/OutcomeRulesSettings.vue'),
    meta: {
        requiresAuth: true,
        layout: 'admin',
        requiredPermission: 'review-cycle-manage',
    },
},
```

**Step 3: Add sidebar link**

In `Sidebar.vue`, under the PERFORMANCE section, add a link to "Outcome Rules" with the Settings icon, pointing to `/admin/performance/outcome-rules`, guarded by `can('review-cycle-manage')`.

**Step 4: Commit**

```bash
git add team-sync-fe/src/views/admin/performance/OutcomeRulesSettings.vue team-sync-fe/src/router/index.js
git commit -m "feat(P6): add Outcome Rules settings page with CRUD"
```

---

## Task 12: Frontend — Outcome display in ReviewDetail.vue

**Files:**
- Modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`

**Step 1: Add outcome section in Overview tab**

After the existing "Review Info Cards" section (around line 596), add a new MainCard that shows the performance outcome when the review is completed and has an outcome applied:

```vue
<!-- Performance Outcome (visible after calibration) -->
<MainCard v-if="review.status === 'completed' && review.outcome_applied_at">
    <h4 class="text-sm font-semibold text-brand-dark mb-4 flex items-center gap-2">
        <TrophyIcon class="w-4 h-4" />
        Performance Outcome
    </h4>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100 text-center">
            <p class="text-xs text-emerald-600 mb-1">Bonus</p>
            <p class="text-lg font-bold text-emerald-700">{{ review.bonus_months }} mo</p>
        </div>
        <div class="p-3 bg-blue-50 rounded-lg border border-blue-100 text-center">
            <p class="text-xs text-blue-600 mb-1">Salary Increase</p>
            <p class="text-lg font-bold text-blue-700">{{ review.salary_increase_pct }}%</p>
        </div>
        <div class="p-3 rounded-lg border text-center" :class="review.promotion_eligible ? 'bg-purple-50 border-purple-100' : 'bg-gray-50 border-gray-100'">
            <p class="text-xs mb-1" :class="review.promotion_eligible ? 'text-purple-600' : 'text-gray-500'">Promotion</p>
            <p class="text-lg font-bold" :class="review.promotion_eligible ? 'text-purple-700' : 'text-gray-400'">
                {{ review.promotion_eligible ? 'Eligible' : 'N/A' }}
            </p>
        </div>
        <div class="p-3 rounded-lg border text-center" :class="review.pip_required ? 'bg-red-50 border-red-100' : 'bg-gray-50 border-gray-100'">
            <p class="text-xs mb-1" :class="review.pip_required ? 'text-red-600' : 'text-gray-500'">PIP</p>
            <p class="text-lg font-bold" :class="review.pip_required ? 'text-red-700' : 'text-gray-400'">
                {{ review.pip_required ? 'Required' : 'Not Required' }}
            </p>
        </div>
    </div>
</MainCard>
```

Add `Trophy` to the lucide-vue-next imports.

**Step 2: Commit**

```bash
git add team-sync-fe/src/views/admin/performance/ReviewDetail.vue
git commit -m "feat(P6): add outcome display in ReviewDetail overview"
```

---

## Task 13: Backend — Include outcome fields in review API response

**Files:**
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php`

**Step 1: Update `getReviewById` eager loading**

In `getReviewById()` (line 94), add `outcomeRule` to the `with` array:

```php
return PerformanceReview::with(['cycle', 'staffMember.user', 'reviewer.user.roles', 'responses.section', 'calibrator', 'outcomeRule'])
    ->findOrFail($id);
```

Also update `calibrateReview()` return (line 191) to include `outcomeRule`:

```php
return $review->fresh()->load(['cycle', 'staffMember.user', 'reviewer.user', 'responses.section', 'calibrator', 'outcomeRule']);
```

**Step 2: Commit**

```bash
git add app/Repositories/PerformanceReviewRepository.php
git commit -m "feat(P6): include outcomeRule in review API responses"
```

---

## Task 14: Update `hris_patch_gap_analysis.md` — Mark P6 tasks complete

**Files:**
- Modify: `team-sync/hris_patch_gap_analysis.md`

**Step 1: Update P6 section**

Change all P6 checkboxes from `- [ ]` to `- [x]` and update the status in the Executive Summary table from `🔴 Not Started` to `✅ Done`.

**Step 2: Commit**

```bash
git add hris_patch_gap_analysis.md
git commit -m "docs: mark P6 tasks complete in gap analysis"
```

---

## Dependency Graph

```
Task 1 (migration: rules table)
  └─→ Task 2 (migration: review outcome fields)
       └─→ Task 3 (models)
            ├─→ Task 4 (seeder)
            └─→ Task 5 (service + tests)
                 └─→ Task 6 (integration into calibrate flow)
                      └─→ Task 13 (eager load outcomeRule)
            ├─→ Task 7 (API resource)
            ├─→ Task 8 (form requests)
            └─→ Task 9 (controller + routes + tests)
                 └─→ Task 10 (FE store actions)
                      ├─→ Task 11 (FE settings page)
                      └─→ Task 12 (FE outcome display)
Task 14 (docs update) — after all above
```

## Execution Waves

| Wave | Tasks | Can Parallelize |
|------|-------|-----------------|
| 1 | Task 1, Task 2 | Sequential (migration order matters) |
| 2 | Task 3 | Depends on Wave 1 |
| 3 | Task 4, Task 5, Task 7, Task 8 | All parallel |
| 4 | Task 6, Task 9 | Parallel (6 needs 5, 9 needs 7+8) |
| 5 | Task 10, Task 13 | Parallel |
| 6 | Task 11, Task 12 | Parallel |
| 7 | Task 14 | Final |
