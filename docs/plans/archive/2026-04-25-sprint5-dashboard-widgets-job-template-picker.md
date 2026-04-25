# Sprint 5: Dashboard Performance Widgets & Job Info Template Picker

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Implement P6-5 (HR Dashboard performance outcome widgets: Promotion Eligible & PIP Required, replacing generic task/project cards) and P7-9 (Performance Template picker in Job Information form).

**Architecture:**
- P6-5: Extend `DashboardRepository::getStatistics()` with `performance` sub-key → expose via existing `/dashboard/statistics` endpoint → update `Statistics.vue` to replace `Tasks Completed` & `Active Projects` cards with `Promotion Eligible` & `PIP Required` using existing `StatsCard` component with matching colorScheme.
- P7-9: Add `review_template_id` to `StaffMemberProfileController::store()` and `update()` validation → update `Step2JobInfo.vue` form to show a template `Select` dropdown → load templates via existing `performanceReview` store → display template name in `StaffMemberDetail.vue` Employment Details card.

**Tech Stack:** Laravel 10, Vue 3 Composition API `<script setup lang="ts">`, Pinia, existing `StatsCard`/`Select` design components, Lucide icons.

---

## Task 1: Backend — Add Performance Outcome Stats to Dashboard

**Files:**
- Modify: `team-sync-be/app/Repositories/DashboardRepository.php` (line 90–145)
- Modify: `team-sync-be/app/Interfaces/DashboardRepositoryInterface.php`

**Step 1: Add performance outcome query to `getStatistics()`**

After the project stats query (line 119), add inside the `cache()->remember` closure:

```php
// Performance outcome stats
$outcomeStats = DB::table('performance_reviews')
    ->where('status', 'completed')
    ->selectRaw("
        COUNT(CASE WHEN promotion_eligible = 1 THEN 1 END) as promotion_eligible_count,
        COUNT(CASE WHEN pip_required = 1 THEN 1 END) as pip_required_count
    ")
    ->first();
```

Then add to the return array:
```php
'performance' => [
    'promotion_eligible' => (int) ($outcomeStats->promotion_eligible_count ?? 0),
    'pip_required'       => (int) ($outcomeStats->pip_required_count ?? 0),
],
```

**Step 2: Run manual check (no migration needed — reads existing columns)**

```bash
cd team-sync-be && php artisan tinker --execute="echo json_encode(app(\App\Interfaces\DashboardRepositoryInterface::class)->getStatistics());"
```

Expected: JSON includes `"performance":{"promotion_eligible":N,"pip_required":N}`

**Step 3: Commit**
```bash
git add app/Repositories/DashboardRepository.php
git commit -m "feat(dashboard): add performance outcome stats (P6-5)"
```

---

## Task 2: Backend — Add `review_template_id` to StaffMember Store/Update

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/StaffMemberProfileController.php`
- Modify: `team-sync-be/app/Http/Requests/StaffMember/StoreStaffMemberRequest.php`
- Modify: `team-sync-be/app/Http/Requests/StaffMember/UpdateStaffMemberRequest.php`

**Step 1: Check current validation in request files**

```bash
grep -n "review_template_id\|team_id\|job_title" team-sync-be/app/Http/Requests/StaffMember/StoreStaffMemberRequest.php
```

**Step 2: Add optional `review_template_id` rule to StoreStaffMemberRequest**

Find the `rules()` method and add:
```php
'review_template_id' => 'nullable|exists:performance_review_templates,id',
```

**Step 3: Add same rule to UpdateStaffMemberRequest**

```php
'review_template_id' => 'nullable|exists:performance_review_templates,id',
```

**Step 4: Verify controller passes `review_template_id` to job_information update**

Check `StaffMemberProfileController` — the `job_information` update must include `review_template_id`. If not, add it to the `job_information` fillable array update block:

```php
// In update/store logic for job_information:
'review_template_id' => $request->review_template_id ?? null,
```

**Step 5: Verify `JobInformation` model has `review_template_id` in `$fillable`**

```bash
grep -n "review_template_id" team-sync-be/app/Models/JobInformation.php
```

Expected: already present (added in P7 sprint).

**Step 6: Write BE feature test for the new field**

In `team-sync-be/tests/Feature/StaffMember/StaffMemberProfileControllerTest.php` (or create if missing), add:

```php
/** @test */
public function hr_can_create_staff_member_with_review_template_id(): void
{
    $this->actingAs($this->hrUser);
    $template = PerformanceReviewTemplate::factory()->create();

    $response = $this->postJson('/api/v1/staff-members', [
        ...$this->validPayload(),
        'review_template_id' => $template->id,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('job_information', ['review_template_id' => $template->id]);
}

/** @test */
public function review_template_id_must_exist(): void
{
    $this->actingAs($this->hrUser);

    $response = $this->postJson('/api/v1/staff-members', [
        ...$this->validPayload(),
        'review_template_id' => 9999,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['review_template_id']);
}
```

**Step 7: Run tests**
```bash
cd team-sync-be && php artisan test --filter StaffMemberProfileControllerTest
```

**Step 8: Commit**
```bash
git add app/Http/Requests/StaffMember/ app/Http/Controllers/StaffMemberProfileController.php tests/
git commit -m "feat(staff-member): support review_template_id on create/update (P7-9)"
```

---

## Task 3: Frontend — Dashboard Widgets Swap (P6-5)

**Files:**
- Modify: `team-sync-fe/src/stores/dashboard.js`
- Modify: `team-sync-fe/src/components/admin/dashboard/Statistics.vue`

**Design System Compliance:**
- Use existing `StatsCard` component (no new components needed)
- `colorScheme="purple"` for Promotion Eligible (icon: `TrendingUpIcon`)
- `colorScheme="red"` for PIP Required (icon: `AlertTriangleIcon`)
- Both follow existing hover border and animation patterns

**Step 1: Add `performance` to dashboard store initial state**

In `dashboard.js`, add to `state`:
```js
performance: {
    promotion_eligible: 0,
    pip_required: 0,
},
```

**Step 2: Update `Statistics.vue` — add computed refs & replace cards**

Add to script:
```ts
const performance = computed(() => dashboardStore.statistics?.performance ?? { promotion_eligible: 0, pip_required: 0 });
```

And import `AlertTriangleIcon` alongside existing icon imports:
```ts
import { TrendingUpIcon, UsersIcon, CalendarCheckIcon, AlertTriangleIcon, FolderIcon, StarIcon } from 'lucide-vue-next';
```

**Step 3: Replace the 2 row-2 StatsCards in template**

Remove:
```html
<!-- Tasks Completed -->
<StatsCard title="Tasks Completed" :value="tasks.completed" ... iconName="CheckSquareIcon" colorScheme="purple" ... />
<!-- Active Projects -->
<StatsCard title="Active Projects" :value="projects.active" ... iconName="FolderIcon" colorScheme="orange" ... />
```

Replace with:
```html
<!-- Promotion Eligible -->
<StatsCard
  title="Promotion Eligible"
  :value="performance.promotion_eligible"
  subtitle="Completed reviews"
  subtitleColor="text-purple-600"
  iconName="TrendingUpIcon"
  colorScheme="purple"
  :loading="loading"
/>

<!-- PIP Required -->
<StatsCard
  title="PIP Required"
  :value="performance.pip_required"
  subtitle="Need improvement plan"
  :subtitleColor="performance.pip_required > 0 ? 'text-danger' : 'text-success'"
  iconName="AlertTriangleIcon"
  colorScheme="red"
  :loading="loading"
/>
```

**Step 4: Write Vitest smoke test**

In `team-sync-fe/src/tests/admin/dashboard/Statistics.smoke.test.js` (create new):

```js
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createTestingPinia } from '@pinia/testing'

describe('Statistics.vue smoke tests', () => {
  it('renders Promotion Eligible card', async () => {
    const wrapper = mount(Statistics, {
      global: {
        plugins: [createTestingPinia({
          createSpy: vi.fn,
          initialState: {
            dashboard: {
              statistics: { performance: { promotion_eligible: 3, pip_required: 1 } },
              loading: false,
            },
          },
        })],
        stubs: { StatsCard: true, MainCard: true, QuickActions: true },
      },
    })
    expect(wrapper.html()).toContain('Promotion Eligible')
    expect(wrapper.html()).toContain('PIP Required')
  })

  it('does NOT render Tasks Completed or Active Projects', async () => {
    // ... same setup
    expect(wrapper.html()).not.toContain('Tasks Completed')
    expect(wrapper.html()).not.toContain('Active Projects')
  })
})
```

**Step 5: Run FE tests**
```bash
cd team-sync-fe && npx vitest run src/tests/admin/dashboard/Statistics.smoke.test.js
```

**Step 6: Commit**
```bash
git add src/stores/dashboard.js src/components/admin/dashboard/Statistics.vue src/tests/admin/dashboard/Statistics.smoke.test.js
git commit -m "feat(dashboard): promotion eligible & PIP required widgets (P6-5)"
```

---

## Task 4: Frontend — Job Info Template Picker (P7-9)

**Files:**
- Modify: `team-sync-fe/src/components/admin/staff-member/create/steps/Step2JobInfo.vue`
- Modify: `team-sync-fe/src/views/admin/staff-member/StaffMemberDetail.vue`
- Modify: `team-sync-fe/src/stores/performanceReview.js` (add `fetchTemplates` if not present)

**Step 1: Verify template list action exists in store**
```bash
grep -n "fetchTemplates\|templates" team-sync-fe/src/stores/performanceReview.js | head -20
```

If `fetchTemplates` is missing, add to `performanceReview.js`:
```js
async fetchTemplates() {
  try {
    const response = await axiosInstance.get('/performance/templates');
    this.templates = response.data.data ?? [];
  } catch (error) {
    this.error = handleError(error);
  }
},
```
And add `templates: []` to state.

**Step 2: Add template picker to `Step2JobInfo.vue`**

In `<script setup>`, import store and add state:
```ts
import { usePerformanceReviewStore } from '@/stores/performanceReview';
import { LayoutTemplate } from 'lucide-vue-next';

const performanceStore = usePerformanceReviewStore();
const templateOptions = computed(() =>
  (performanceStore.templates ?? []).map(t => ({
    value: t.id,
    label: t.name + (t.is_default ? ' (Default)' : ''),
  }))
);
```

In `onMounted`, add parallel fetch:
```ts
await Promise.allSettled([
  // ...existing fetches...
  performanceStore.fetchTemplates(),
]);
```

In the Job Information section grid (after `monthly_salary` field), add:
```html
<!-- Performance Template (optional) -->
<div class="mb-4">
  <Select
    id="review_template_id"
    name="review_template_id"
    v-model="form.review_template_id"
    label="Performance Template"
    placeholder="Use default template"
    :options="templateOptions"
    :error="errors?.review_template_id?.join(', ')"
  >
    <template #icon>
      <LayoutTemplate class="h-5 w-5 text-gray-400" />
    </template>
  </Select>
  <p class="text-brand-light text-xs mt-1">
    Determines which review sections and weights apply to this employee
  </p>
</div>
```

**Step 3: Show template name in `StaffMemberDetail.vue` Employment Details card**

In the Employment Details `div.space-y-4` (around line 545), add after Employment Type:
```html
<div class="flex justify-between items-center">
  <span class="text-brand-light text-base">Performance Template</span>
  <span class="text-brand-dark text-base font-medium">
    {{ staffMember.job_information?.review_template?.name ?? 'Default Template' }}
  </span>
</div>
```

**Step 4: Write Vitest smoke test for template picker**

In `team-sync-fe/src/tests/admin/staff-member/Step2JobInfo.smoke.test.js` (create/extend):

```js
it('renders Performance Template dropdown', async () => {
  // mount Step2JobInfo with performanceReview store having templates
  // assert Select with id="review_template_id" is present
  // assert placeholder text "Use default template" is visible
})

it('is optional — no error if review_template_id is empty', async () => {
  // submit without review_template_id, assert no validation error for it
})
```

**Step 5: Run FE tests**
```bash
cd team-sync-fe && npx vitest run src/tests/admin/
```

**Step 6: Commit**
```bash
git add src/components/admin/staff-member/create/steps/Step2JobInfo.vue \
         src/views/admin/staff-member/StaffMemberDetail.vue \
         src/stores/performanceReview.js \
         src/tests/
git commit -m "feat(staff-member): performance template picker in job info (P7-9)"
```

---

## Task 5: Full Verification

**Step 1: Run complete Vitest suite**
```bash
cd team-sync-fe && npx vitest run
```
Expected: All 193+ tests pass (0 failures).

**Step 2: Run BE tests**
```bash
cd team-sync-be && php artisan test --filter "DashboardControllerTest|StaffMemberProfileControllerTest"
```
Expected: All pass.

**Step 3: Frontend build check**
```bash
cd team-sync-fe && npm run build
```
Expected: exit 0.

**Step 4: Update artifacts**
- Update `hris_patch_overview.md` → mark P6-5 and P7-9 ✅
- Update `task_tracker.md` → move Sprint 5 items to Done

**Step 5: Final commit**
```bash
git add -A
git commit -m "chore: complete Sprint 5 — dashboard widgets + job info template picker"
```
