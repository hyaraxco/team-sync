# Performance Module Auth & Metric Card Styling Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Resolve the 403 `UnauthorizedException` in the Performance module for Finance/cross-functional roles by expanding the baseline self-service permissions, and update the existing metric card styling using `Statistics.vue` as a reference.

**Architecture:** We will update `RolePermissionSeeder.php` to include fundamental performance abilities in the `$selfServiceBaseline` array so all seeded roles receive them. Then we will apply styling adjustments to the `MetricCard` on the frontend.

**Tech Stack:** Laravel, Vue 3, Tailwind CSS.

---

### Task 1: Update Seeder for Baseline Performance Permissions

**Files:**
- Modify: `/Users/hyarax/Documents/project/team-sync/team-sync-be/database/seeders/RolePermissionSeeder.php`

**Step 1: Write the minimal implementation**

We need to add the performance menu and basic action permissions to the `$selfServiceBaseline` variable within `database/seeders/RolePermissionSeeder.php`.

```php
            $selfServiceBaseline = [
                'profile-menu',
                'profile-view',
                'attendance-my-attendances',
                'attendance-last-attendance',
                'attendance-check-in',
                'attendance-check-out',
                'attendance-correction-create',
                'leave-request-menu',
                'leave-request-create',
                'leave-request-my-requests',
                'payslip-view',
                // New additions for performance self-service baseline
                'performance-menu',
                'review-self-submit',
                'goal-create-own',
                'feedback-give',
            ];
```

Note: Because we add it to the baseline, we can safely remove them from the explicit `Employee` array further down (lines 142-146) to keep the seeder DRY, as the baseline is merged into Employee explicitly or implicitly.

**Step 2: Commit**

```bash
cd /Users/hyarax/Documents/project/team-sync/team-sync-be
git add database/seeders/RolePermissionSeeder.php
git commit -m "fix: add performance self-service to baseline permissions for all roles"
```

---

### Task 2: Reseed Database

**Step 1: Refresh Database Seeders**

Because permission definitions heavily rely on cache in Spatie/Laravel, we need to clear cache and re-run the `RolePermissionSeeder` using sail.

```bash
cd /Users/hyarax/Documents/project/team-sync/team-sync-be
./vendor/bin/sail artisan optimize:clear
./vendor/bin/sail artisan db:seed --class=RolePermissionSeeder
```
*(If sail is unavailable we will use standard artisan inside the backend execution context)*

---

### Task 3: Refine `MetricCard` Subtitle (Frontend)

**Files:**
- Modify: `/Users/hyarax/Documents/project/team-sync/team-sync-fe/src/components/admin/analytics/MetricCard.vue`

**Step 1: Write the Glass Badge Implementation**

We previously decided on the Glass Badge, but considering your feedback regarding `Statistics.vue` layout references, we will ensure it perfectly matches the `MainCard` layout there (which often uses subtle `text-brand-white-70` and icons aligned carefully).

We'll update the subtitle area for the `highlight` mode.

```vue
            <p
              v-if="subtitle"
              :class="
                highlight
                  ? 'inline-flex items-center px-2.5 py-1 mt-3 rounded-[8px] bg-white/10 backdrop-blur-md border border-white/5 text-[13px] font-medium text-white/70'
                  : 'text-sm font-medium text-[#8F8F8F]'
              "
            >
              {{ subtitle }}
            </p>
```

**Step 2: Commit**

```bash
cd /Users/hyarax/Documents/project/team-sync/team-sync-fe
git add src/components/admin/analytics/MetricCard.vue
git commit -m "style: implement glass badge for metric card subtitle referencing MainCard aesthetics"
```

---

### Task 4: Streamline Global Card CSS (Frontend)

**Files:**
- Modify: `/Users/hyarax/Documents/project/team-sync/team-sync-fe/src/assets/css/main.css`

**Step 1: Refine `main-card` CSS Gradient**

```css
.main-card {
  background: linear-gradient(135deg, #0B1238 0%, #04081E 100%);
  box-shadow: 
    inset 0 1px 1px 0 rgba(255, 255, 255, 0.05),
    0 10px 15px -3px rgba(0, 0, 0, 0.1), 
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
```

**Step 2: Commit**

```bash
cd /Users/hyarax/Documents/project/team-sync/team-sync-fe
git add src/assets/css/main.css
git commit -m "style: optimize global main-card gradient and shadow"
```

---

### Task 5: Clean up `PayrollAnalyticsEnhanced` Wrapper

**Files:**
- Modify: `/Users/hyarax/Documents/project/team-sync/team-sync-fe/src/components/admin/analytics/PayrollAnalyticsEnhanced.vue`

**Step 1: Remove redundant classes**

Remove classes that conflict or double up with the internal `MetricCard` styles.

```vue
      <MetricCard
        class="lg rounded-[20px] main-card"
        title="Total Payroll Cost"
        :value="payroll?.total_payroll_cost || 0"
        format="currency"
        :trend="payroll?.payroll_cost_change"
        subtitle="This period"
        :loading="payrollLoading"
        highlight
      />
```

**Step 2: Commit**

```bash
cd /Users/hyarax/Documents/project/team-sync/team-sync-fe
git add src/components/admin/analytics/PayrollAnalyticsEnhanced.vue
git commit -m "refactor: clean up redundant wrapper classes referencing main-card"
```

---

Plan complete and saved to `docs/plans/2026-04-20-performance-auth-and-metrics.md`.

Next step: use the **executing-plans** skill to execute this plan task-by-task in single-flow mode.
