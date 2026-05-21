# Onboarding Enhancements (Future Plan)

> **Status:** Future / Not Scheduled. Both items are PRD §2.1 onboarding polish, not blockers.

**Goal:** Complete the first-boot UX experience per PRD §2.1 by adding optional sample data seeding and a quick feature tour for first-time users.

**Architecture:**
- **Sample Data Seeding** — Add `POST /api/v1/setup/seed-demo` endpoint and an optional/skippable step 4 in `SetupWizard.vue` after superadmin bootstrap.
- **Quick Tour** — Standalone `AppTour.vue` overlay component with localStorage gate, mounted in Admin layout.

**Tech Stack:** Laravel 12, Vue 3 Composition API, Pinia, Tailwind, Pest (BE), Vitest (FE).

**Priority:**
- Sample Data: MEDIUM — useful for demos but not blocking
- Quick Tour: LOW — nice onboarding polish, no functional gap

---

## Why Future, Not On_Going

- Setup wizard already works end-to-end without these (3 steps complete bootstrap successfully).
- TOPSIS criteria fix (`docs/plans/on_going/2026-05-20-topsis-criteria-fix.md`) is the only audit finding requiring immediate execution.
- These two share a domain (post-bootstrap UX) and should ship together when prioritized.

---

## Task 1: Sample Data Seeding (BE + FE)

**Files:**
- Modify: `team-sync-be/routes/api.php` (add route inside `auth:sanctum` group)
- Modify: `team-sync-be/app/Http/Controllers/SetupController.php` (add `seedDemo` method + import)
- Test BE: `team-sync-be/tests/Feature/Setup/SeedDemoTest.php` (new)
- Modify: `team-sync-fe/src/stores/setup.js` (add `seedDemoLoading` state + `seedDemo` action)
- Modify: `team-sync-fe/src/views/setup/SetupWizard.vue` (add step 4)
- Test FE: `team-sync-fe/src/tests/views/SetupWizardSeedDemo.smoke.test.js` (new)

**Existing assets:**
- `DemoDataSeeder.php` already exists, idempotent (uses `updateOrCreate`), seeds 5 users + 3 teams + 2 projects.

### Steps

- [ ] **Step 1: Write failing BE feature test**

Create `team-sync-be/tests/Feature/Setup/SeedDemoTest.php`:

```php
<?php

use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

it('seeds demo data for authenticated superadmin', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $response = $this->actingAs($superadmin)->postJson('/api/v1/setup/seed-demo');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['users_seeded', 'teams_seeded']]);
});

it('rejects seed-demo for unauthenticated user', function () {
    $this->postJson('/api/v1/setup/seed-demo')->assertUnauthorized();
});

it('seed-demo is idempotent (safe to run multiple times)', function () {
    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $this->actingAs($superadmin)->postJson('/api/v1/setup/seed-demo')->assertOk();
    $this->actingAs($superadmin)->postJson('/api/v1/setup/seed-demo')->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-be && composer test -- --filter=SeedDemoTest`
Expected: FAIL — route not registered (404)

- [ ] **Step 3: Add route to routes/api.php**

Inside the `Route::middleware(['auth:sanctum'])->group(function () {` block in `team-sync-be/routes/api.php`, add near the top:

```php
            Route::post('setup/seed-demo', [SetupController::class, 'seedDemo'])->middleware('throttle:5,1');
```

- [ ] **Step 4: Add seedDemo method to SetupController**

In `team-sync-be/app/Http/Controllers/SetupController.php`:

Add import after existing seeders:

```php
use Database\Seeders\DemoDataSeeder;
```

Add method after `bootstrap()`:

```php
    /**
     * Seed demo data (sample users, teams, projects).
     * Idempotent — safe to run multiple times. Requires authenticated user.
     */
    public function seedDemo()
    {
        try {
            DB::beginTransaction();

            Artisan::call('db:seed', [
                '--class' => DemoDataSeeder::class,
                '--force' => true,
            ]);

            DB::commit();

            return ResponseHelper::jsonResponse(true, 'Sample data seeded successfully.', [
                'users_seeded' => User::count(),
                'teams_seeded' => \App\Models\Team::count(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('SetupController seedDemo error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Failed to seed demo data. Check server logs.', null, 500);
        }
    }
```

- [ ] **Step 5: Run BE tests to verify pass**

Run: `cd team-sync-be && composer test -- --filter=SeedDemoTest`
Expected: PASS

- [ ] **Step 6: Update setup.js store**

In `team-sync-fe/src/stores/setup.js`:

Add to state (after `bootstrapLoading: false,`):

```javascript
        // Seed demo
        seedDemoResult: null,
        seedDemoLoading: false,
```

Add action after `bootstrap()`:

```javascript
        async seedDemo() {
            this.seedDemoLoading = true;
            this.seedDemoResult = null;
            this.error = null;

            try {
                const response = await axiosInstance.post("/setup/seed-demo");
                this.seedDemoResult = response.data.data;
                return response.data.data;
            } catch (error) {
                this.error = handleError(error);
                throw error;
            } finally {
                this.seedDemoLoading = false;
            }
        },
```

- [ ] **Step 7: Update SetupWizard.vue — add step 4**

In `team-sync-fe/src/views/setup/SetupWizard.vue`:

Update import (add `Database`, `SkipForward`):

```javascript
import {
    KeyRound,
    Stethoscope,
    UserPlus,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    RefreshCw,
    ArrowRight,
    ArrowLeft,
    PartyPopper,
    Database,
    SkipForward,
} from "lucide-vue-next";
```

Change `totalSteps` (line 26):

```javascript
const totalSteps = 4;
```

Update `stepConfig` (line 60):

```javascript
const stepConfig = computed(() => [
    { number: 1, label: "Lisensi", icon: KeyRound },
    { number: 2, label: "Kesehatan Sistem", icon: Stethoscope },
    { number: 3, label: "Akun Admin", icon: UserPlus },
    { number: 4, label: "Data Demo", icon: Database },
]);
```

Update `submitBootstrap` (line 117) — replace `setupComplete.value = true;` with:

```javascript
        currentStep.value = 4;
```

Add new handlers after `submitBootstrap`:

```javascript
const seedDemoData = async () => {
    setupStore.resetError();
    try {
        await setupStore.seedDemo();
        toast.success("Data Demo Dimuat", "Sample data berhasil ditambahkan.");
        setupComplete.value = true;
    } catch {
        toast.error("Gagal", setupStore.error || "Tidak dapat memuat data demo.");
    }
};

const skipSeedDemo = () => {
    setupComplete.value = true;
};
```

Add step 4 template block after step 3 closing `</div>` (before "Setup Complete" block):

```html
            <!-- Step 4: Seed Demo Data (Optional) -->
            <div
                v-if="currentStep === 4 && !setupComplete"
                class="bg-white rounded-3xl border border-gray-200 p-6 sm:p-8 shadow-sm"
            >
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <Database class="w-8 h-8 text-amber-600" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Muat Data Demo? (Opsional)</h2>
                    <p class="text-gray-500 text-sm mt-2">
                        Tambahkan sample karyawan, tim, dan proyek untuk eksplorasi cepat. Anda dapat melewati ini dan
                        memulai dengan data kosong.
                    </p>
                </div>

                <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900 mb-6">
                    <p class="font-semibold mb-2">Yang akan dimuat:</p>
                    <ul class="list-disc list-inside space-y-1 text-blue-800">
                        <li>5 user contoh (HR, Manager, Finance, Staff)</li>
                        <li>3 tim dengan anggota</li>
                        <li>2 proyek dengan task</li>
                    </ul>
                </div>

                <div
                    v-if="setupStore.error"
                    class="rounded-xl bg-red-50 border border-red-200 p-3 text-sm text-red-700 mb-4"
                >
                    {{ typeof setupStore.error === "string" ? setupStore.error : "Gagal memuat data." }}
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:border-brand-primary"
                        :disabled="setupStore.seedDemoLoading"
                        @click="skipSeedDemo"
                    >
                        <SkipForward class="w-4 h-4" />
                        Lewati
                    </button>
                    <button
                        type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 disabled:opacity-60 transition-all"
                        :disabled="setupStore.seedDemoLoading"
                        @click="seedDemoData"
                    >
                        <RefreshCw v-if="setupStore.seedDemoLoading" class="w-4 h-4 animate-spin" />
                        <template v-else>
                            <Database class="w-4 h-4" />
                            Muat Data Demo
                            <ArrowRight class="w-4 h-4" />
                        </template>
                    </button>
                </div>
            </div>
```

- [ ] **Step 8: Write FE smoke test**

Create `team-sync-fe/src/tests/views/SetupWizardSeedDemo.smoke.test.js`:

```javascript
import { describe, it, expect, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import SetupWizard from "@/views/setup/SetupWizard.vue";

describe("SetupWizard step 4 (seed demo)", () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it("renders step 4 with seed and skip buttons", async () => {
        const wrapper = mount(SetupWizard, {
            global: {
                stubs: { RouterLink: true },
                mocks: { $route: { name: "setup" } },
            },
        });

        await flushPromises();

        wrapper.vm.currentStep = 4;
        await wrapper.vm.$nextTick();

        const html = wrapper.html();
        expect(html).toContain("Muat Data Demo");
        expect(html).toContain("Lewati");
    });
});
```

- [ ] **Step 9: Run all tests**

Run: `cd team-sync-fe && bun run test -- SetupWizardSeedDemo`
Expected: PASS

Run: `cd team-sync-be && composer test`
Expected: PASS — no regressions

- [ ] **Step 10: Format and commit**

```bash
cd team-sync-be && ./vendor/bin/pint
cd team-sync-fe && bun run format

git checkout -b feat/setup-seed-demo
git add team-sync-be/routes/api.php \
        team-sync-be/app/Http/Controllers/SetupController.php \
        team-sync-be/tests/Feature/Setup/SeedDemoTest.php \
        team-sync-fe/src/stores/setup.js \
        team-sync-fe/src/views/setup/SetupWizard.vue \
        team-sync-fe/src/tests/views/SetupWizardSeedDemo.smoke.test.js
git commit -m "feat(setup): add optional sample data seeding to setup wizard"
```

---

## Task 2: Quick Tour (FE Only)

**Files:**
- Create: `team-sync-fe/src/components/common/AppTour.vue`
- Modify: `team-sync-fe/src/layouts/Admin.vue` (mount AppTour)
- Test: `team-sync-fe/src/tests/components/AppTour.smoke.test.js` (new)

### Steps

- [ ] **Step 1: Write failing FE test**

Create `team-sync-fe/src/tests/components/AppTour.smoke.test.js`:

```javascript
import { describe, it, expect, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import AppTour from "@/components/common/AppTour.vue";

describe("AppTour", () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it("renders when localStorage flag not set", () => {
        const wrapper = mount(AppTour);
        expect(wrapper.html()).toContain("Selamat Datang");
    });

    it("does not render when tour already completed", () => {
        localStorage.setItem("team_sync_tour_completed", "1");
        const wrapper = mount(AppTour);
        expect(wrapper.html()).not.toContain("Selamat Datang");
    });

    it("advances through steps and completes on final step", async () => {
        const wrapper = mount(AppTour);

        expect(wrapper.text()).toContain("Selamat Datang");

        await wrapper.find("[data-tour-next]").trigger("click");
        await wrapper.find("[data-tour-next]").trigger("click");
        await wrapper.find("[data-tour-next]").trigger("click");

        await wrapper.find("[data-tour-finish]").trigger("click");

        expect(localStorage.getItem("team_sync_tour_completed")).toBe("1");
        expect(wrapper.html()).not.toContain("Selamat Datang");
    });

    it("dismisses on skip and persists flag", async () => {
        const wrapper = mount(AppTour);

        await wrapper.find("[data-tour-skip]").trigger("click");

        expect(localStorage.getItem("team_sync_tour_completed")).toBe("1");
        expect(wrapper.html()).not.toContain("Selamat Datang");
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd team-sync-fe && bun run test -- AppTour.smoke`
Expected: FAIL — component does not exist

- [ ] **Step 3: Create AppTour.vue**

Create `team-sync-fe/src/components/common/AppTour.vue`:

```vue
<script setup>
import { ref, computed } from "vue";
import {
    LayoutDashboard,
    Users,
    CalendarCheck,
    ArrowRight,
    ArrowLeft,
    X,
    PartyPopper,
} from "lucide-vue-next";

const STORAGE_KEY = "team_sync_tour_completed";

const isCompleted = ref(localStorage.getItem(STORAGE_KEY) === "1");
const currentStep = ref(0);

const steps = [
    {
        icon: PartyPopper,
        iconBg: "bg-purple-50",
        iconColor: "text-purple-600",
        title: "Selamat Datang di Team Sync",
        description:
            "Mari berkenalan dengan fitur utama. Tour singkat ini akan memandu Anda melalui 4 modul kunci.",
    },
    {
        icon: LayoutDashboard,
        iconBg: "bg-blue-50",
        iconColor: "text-blue-600",
        title: "Dashboard",
        description:
            "Pusat informasi harian — statistik karyawan, kehadiran, dan ringkasan tim. Akses dari sidebar kiri.",
    },
    {
        icon: Users,
        iconBg: "bg-green-50",
        iconColor: "text-green-600",
        title: "Manajemen Karyawan",
        description:
            "Kelola data karyawan, profil, jabatan, dan tim. Tambah karyawan baru atau edit informasi yang sudah ada.",
    },
    {
        icon: CalendarCheck,
        iconBg: "bg-amber-50",
        iconColor: "text-amber-600",
        title: "Kehadiran & Penggajian",
        description:
            "Pantau check-in/out, kelola cuti, dan generate payroll bulanan. Semua dengan kalkulasi BPJS dan PPh 21 otomatis.",
    },
];

const totalSteps = steps.length;
const currentStepData = computed(() => steps[currentStep.value]);
const isLastStep = computed(() => currentStep.value === totalSteps - 1);

const dismiss = () => {
    localStorage.setItem(STORAGE_KEY, "1");
    isCompleted.value = true;
};

const next = () => {
    if (currentStep.value < totalSteps - 1) {
        currentStep.value++;
    }
};

const previous = () => {
    if (currentStep.value > 0) {
        currentStep.value--;
    }
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="!isCompleted"
            class="fixed inset-0 z-[9999] bg-black/50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="tour-title"
        >
            <div class="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 relative">
                <button
                    type="button"
                    class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 rounded-lg p-1"
                    aria-label="Tutup tour"
                    data-tour-skip
                    @click="dismiss"
                >
                    <X class="w-5 h-5" />
                </button>

                <div class="text-center mb-6">
                    <div
                        class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                        :class="currentStepData.iconBg"
                    >
                        <component
                            :is="currentStepData.icon"
                            class="w-8 h-8"
                            :class="currentStepData.iconColor"
                        />
                    </div>
                    <h2 id="tour-title" class="text-xl font-bold text-gray-900">
                        {{ currentStepData.title }}
                    </h2>
                    <p class="text-gray-500 text-sm mt-2">{{ currentStepData.description }}</p>
                </div>

                <div class="flex items-center justify-center gap-1.5 mb-6" aria-hidden="true">
                    <span
                        v-for="(s, idx) in steps"
                        :key="idx"
                        class="w-2 h-2 rounded-full transition-colors"
                        :class="idx === currentStep ? 'bg-brand-primary' : 'bg-gray-200'"
                    ></span>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        v-if="currentStep > 0"
                        type="button"
                        class="flex items-center gap-2 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:border-brand-primary"
                        @click="previous"
                    >
                        <ArrowLeft class="w-4 h-4" />
                        Kembali
                    </button>

                    <button
                        v-if="!isLastStep"
                        type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 transition-all"
                        data-tour-next
                        @click="next"
                    >
                        Selanjutnya
                        <ArrowRight class="w-4 h-4" />
                    </button>

                    <button
                        v-else
                        type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-brand-primary px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110 transition-all"
                        data-tour-finish
                        @click="dismiss"
                    >
                        <PartyPopper class="w-4 h-4" />
                        Mulai Eksplorasi
                    </button>
                </div>

                <p class="text-xs text-gray-400 text-center mt-4">
                    Langkah {{ currentStep + 1 }} dari {{ totalSteps }}
                </p>
            </div>
        </div>
    </Teleport>
</template>
```

- [ ] **Step 4: Mount AppTour in Admin layout**

In `team-sync-fe/src/layouts/Admin.vue`:

Add import:

```javascript
import AppTour from "@/components/common/AppTour.vue";
```

Add `<AppTour />` after the closing `</main>` div, before the mobile overlay div:

```html
            <main class="main-content flex-1 overflow-auto p-3 sm:p-4 md:p-6 lg:p-8">
                <ErrorBoundary>
                    <RouterView />
                </ErrorBoundary>
            </main>
        </div>

        <AppTour />

        <div class="fixed inset-0 bg-black/30 lg:hidden" v-if="isOpen" @click="closeMobile"></div>
    </div>
</template>
```

- [ ] **Step 5: Run tests to verify pass**

Run: `cd team-sync-fe && bun run test -- AppTour.smoke`
Expected: PASS

Run full FE suite: `cd team-sync-fe && bun run test`
Expected: PASS — no regressions

- [ ] **Step 6: Format and commit**

```bash
cd team-sync-fe && bun run format
git checkout -b feat/onboarding-tour
git add team-sync-fe/src/components/common/AppTour.vue \
        team-sync-fe/src/layouts/Admin.vue \
        team-sync-fe/src/tests/components/AppTour.smoke.test.js
git commit -m "feat(onboarding): add quick tour for first-time users"
```

---

## Final Verification (when both tasks shipped)

- [ ] **Run full BE test suite**

```bash
cd team-sync-be && composer test
```

Expected: 1500+ tests passing

- [ ] **Run full FE test suite**

```bash
cd team-sync-fe && bun run test
```

Expected: 1020+ tests passing

- [ ] **Manual smoke test**

1. Reset DB → run setup wizard end-to-end → verify step 4 appears + seeds demo
2. Login as new superadmin → verify tour appears → click through all 4 steps
3. Reload → tour should NOT reappear (localStorage flag)

---

## Notes

- **Tour gating uses localStorage** — per-browser, per-user. No backend state. Acceptable trade-off for v1; for cross-device tracking, add `tour_completed_at` to user profile.
- **Future enhancement**: target-element-based tour (highlight specific sidebar items) requires DOM refs in `Sidebar.vue`. Out of scope for this plan.
- **Tour content currently hardcoded in component** — if i18n is added, extract steps array to translation files.
- **Sample data is opt-in** — wizard step 4 is skippable; existing users with no demo step lose nothing.
