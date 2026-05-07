# PLAN — Fix Reviewer Chain Assignment

> Status: DRAFT — Menunggu approval sebelum implementasi
> Tanggal: 2026-04-28
> Scope: Backend guards + seed data + repair command
> Estimasi: 2-4 jam

---

## 1. Masalah yang Ditemukan

### 1.1 Data Review Saat Ini (Bukti Runtime)

| # | Employee | Role | Reviewer | Role Reviewer | Masalah |
|---|----------|------|----------|---------------|---------|
| 2 | Yudhis | manager | Yudhis | manager | Manager mereview dirinya sendiri |
| 3 | Tasyia | hr | Yudhis | manager | HR direview oleh Manager biasa |
| 6 | Yudhis | manager | Agung | staff | Manager direview oleh Staff |
| 8 | Tasyia | hr | - | UNASSIGNED | Belum ada reviewer |
| 9 | Dwimeta | finance | - | UNASSIGNED | Belum ada reviewer |

### 1.2 Reviewer Rules di DB

Hanya 1 rule ter-seed dari 4 yang seharusnya:

| Seharusnya | Status |
|------------|--------|
| staff -> manager | Ada |
| manager -> hr | TIDAK ADA |
| finance -> hr | TIDAK ADA |
| hr -> hr (cross, exclude self) | TIDAK ADA |

### 1.3 Akar Masalah

1. ReviewerRuleSeeder belum dijalankan ulang setelah rules ditambahkan
2. PerformanceDataSeeder hardcode reviewer_id = managerProfile untuk SEMUA review (termasuk manager sendiri dan HR)
3. Tidak ada guard self-review di generateReviews(), createReview(), dan assignReviewer()
4. submitManagerAssessment() tidak cek apakah user yang submit adalah reviewer yang ditunjuk

---

## 2. Hierarki Reviewer yang Benar (dari SPEC)

```
Staff/Employee  -> direview oleh -> Manager (via team/direct)
Manager         -> direview oleh -> HR
Finance         -> direview oleh -> HR
HR              -> direview oleh -> HR lain (exclude diri sendiri)
```

Jika resolver tidak menemukan reviewer yang valid -> reviewer_id = null (HR harus assign manual via UI).

---

## 3. Komponen yang Sudah Ada dan Benar

| Komponen | Status | Catatan |
|----------|--------|---------|
| ReviewerResolverService | BENAR | Resolve berdasarkan reviewer_rules, prefer same-team, exclude self |
| ReviewerRule model + migration | BENAR | Tabel reviewer_rules sudah ada |
| ReviewerRuleSeeder | BENAR | 4 rules sudah didefinisikan, tapi belum ter-seed di DB |
| generateReviews() | SEBAGIAN BENAR | Sudah panggil resolveMany(), tapi tidak guard self-review |
| calibrateReview() | BENAR | Sudah ada guard: abort 403 jika calibrator === reviewee |
| getPendingCalibration() | BENAR | Sudah exclude review milik sendiri |
| FE canSubmitManagerAssessment | BENAR | Cek currentEmployeeId === review.reviewer_id |
| FE GeneratedReviewsList | BENAR | HR bisa override reviewer via UI |

---

## 4. Task List

### Task 1: Reseed reviewer_rules (DB sync)

Jalankan ReviewerRuleSeeder agar 4 rules lengkap di DB.

Acceptance criteria:
- reviewer_rules table punya 4 rows aktif
- Idempotent (updateOrCreate, sudah benar di seeder)

Perintah:
```bash
docker compose exec -T web php artisan db:seed --class=ReviewerRuleSeeder
```

---

### Task 2: Guard self-review di semua entry point assignment

Tambah validasi reviewer_id !== staff_member_id di 3 tempat:

**2a. generateReviews() — PerformanceReviewCycleController**

Lokasi: team-sync-be/app/Http/Controllers/PerformanceReviewCycleController.php line 86

Saat ini:
```php
$reviewerId = $assignments[$staffMember->id];
```

Ubah ke:
```php
$reviewerId = $assignments[$staffMember->id];
// Guard: jangan assign diri sendiri sebagai reviewer
if ($reviewerId === $staffMember->id) {
    $reviewerId = null;
}
```

**2b. assignReviewer() — PerformanceReviewController**

Lokasi: team-sync-be/app/Http/Controllers/PerformanceReviewController.php line 308

Tambah sebelum updateReview:
```php
if ($request->validated('reviewer_id') == $review->staff_member_id) {
    return ResponseHelper::jsonResponse(false, 'Cannot assign employee as their own reviewer.', null, 422);
}
```

**2c. createReview() — PerformanceReviewRepository**

Lokasi: team-sync-be/app/Repositories/PerformanceReviewRepository.php line 104

Tambah validasi:
```php
public function createReview(array $data)
{
    if (isset($data['reviewer_id']) && $data['reviewer_id'] == $data['staff_member_id']) {
        $data['reviewer_id'] = null; // Force manual assignment
    }
    return PerformanceReview::create($data);
}
```

Acceptance criteria:
- generateReviews tidak pernah menghasilkan self-review
- assignReviewer menolak self-assignment dengan 422
- createReview otomatis null-kan reviewer jika self-assign

---

### Task 3: Guard ownership di submitManagerAssessment

Lokasi: team-sync-be/app/Http/Controllers/PerformanceReviewController.php line 116

Tambah setelah line 118 (sebelum repository call):
```php
$review = $this->repository->getReviewById($id);
$currentStaffId = Auth::user()->staffMemberProfile?->id;
if ($review->reviewer_id !== $currentStaffId) {
    return ResponseHelper::jsonResponse(false, 'Only the assigned reviewer can submit manager assessment.', null, 403);
}
```

Acceptance criteria:
- Hanya reviewer yang ditunjuk yang bisa submit manager assessment
- User lain (meskipun punya role manager/hr) mendapat 403

---

### Task 4: Fix PerformanceDataSeeder

Lokasi: team-sync-be/database/seeders/PerformanceDataSeeder.php

Ubah hardcoded reviewer_id agar menggunakan ReviewerResolverService:

```php
$resolver = app(ReviewerResolverService::class);

// Review 1: Agung (staff) -> resolver finds manager
$review1Reviewer = $resolver->resolve($staffMemberProfile)?->id;

// Review 2: Yudhis (manager) -> resolver finds HR
$review2Reviewer = $resolver->resolve($managerProfile)?->id;

// Review 3: Tasyia (hr) -> resolver finds other HR (or null)
$review3Reviewer = $resolver->resolve($hrProfile)?->id;

// Review 5: Dwimeta (finance) -> resolver finds HR
$review5Reviewer = $resolver->resolve($financeProfile)?->id;
```

Acceptance criteria:
- Seeder tidak pernah hardcode reviewer_id = managerProfile
- Seeder menggunakan resolver for setiap review
- Jika resolver return null, reviewer_id = null (HR assign manual)

---

### Task 5: Artisan command untuk repair existing bad assignments

Buat command: php artisan reviews:fix-reviewers

Logika:
1. Ambil semua review yang BELUM di-submit manager assessment (status: pending_self, pending_manager)
2. Untuk setiap review, panggil ReviewerResolverService::resolve()
3. Jika reviewer baru berbeda dari yang lama, update
4. Jika reviewer === reviewee (self-review), set null
5. JANGAN sentuh review yang sudah: pending_calibration, completed, cancelled
6. Mode --dry-run: tampilkan perubahan tanpa apply
7. Mode default: apply perubahan + tampilkan summary

Output contoh:
```
[DRY RUN] Review #2: Yudhis (manager) reviewer Yudhis->Tasyia (hr)
[DRY RUN] Review #3: Tasyia (hr) reviewer Yudhis->null (no valid HR peer)
[DRY RUN] Review #6: Yudhis (manager) reviewer Agung->Tasyia (hr)
```

Acceptance criteria:
- Hanya review dengan status pending_self atau pending_manager yang diubah
- Review completed/calibrated TIDAK disentuh
- --dry-run menampilkan tanpa mengubah data
- Tanpa flag langsung apply

---

### Task 6: Test coverage

Tambah/update test untuk:

1. generateReviews tidak menghasilkan self-review
2. assignReviewer menolak self-assignment (422)
3. submitManagerAssessment menolak non-reviewer (403)
4. ReviewerResolverService: staff->manager, manager->hr, finance->hr, hr->hr(exclude self)
5. Edge case: hanya 1 HR -> hr->hr resolver return null
6. Repair command: hanya ubah pending reviews, skip completed

Lokasi test: team-sync-be/tests/Feature/Performance/

Acceptance criteria:
- Semua test baru pass
- Existing 422 test tetap pass
- Total test suite tetap hijau

---

## 5. File yang Akan Diubah

| File | Perubahan |
|------|-----------|
| team-sync-be/database/seeders/PerformanceDataSeeder.php | Gunakan resolver, bukan hardcode |
| team-sync-be/app/Http/Controllers/PerformanceReviewCycleController.php | Guard self-review di generateReviews |
| team-sync-be/app/Http/Controllers/PerformanceReviewController.php | Guard self-assign + ownership submitManagerAssessment |
| team-sync-be/app/Repositories/PerformanceReviewRepository.php | Guard self-review di createReview |
| team-sync-be/app/Console/Commands/FixReviewerAssignments.php | NEW: repair command |
| team-sync-be/tests/Feature/Performance/ReviewerChainTest.php | NEW: test coverage |

---

## 6. Yang TIDAK Diubah

- ReviewerResolverService — sudah benar
- ReviewerRule model — sudah benar
- ReviewerRuleSeeder — sudah benar (hanya perlu dijalankan)
- FE components — sudah benar (canSubmitManagerAssessment cek reviewer_id)
- calibrateReview guard — sudah benar
- getPendingCalibration exclusion — sudah benar

---

## 7. Urutan Eksekusi

```
1. Task 1: Reseed reviewer_rules          (1 command)
2. Task 4: Fix PerformanceDataSeeder      (code change)
3. Task 2: Guard self-review (3 files)    (code change)
4. Task 3: Guard ownership submit         (code change)
5. Task 6: Test coverage                  (code change)
6. Task 5: Repair command                 (code change)
7. Run: php artisan reviews:fix-reviewers --dry-run
8. Review dry-run output
9. Run: php artisan reviews:fix-reviewers
10. Run: php artisan test (full suite)
```

---

## 8. Risiko dan Mitigasi

| Risiko | Mitigasi |
|--------|----------|
| Re-resolve mengubah review yang sudah di-assess | Hanya sentuh pending_self dan pending_manager |
| Hanya 1 HR, hr->hr return null | reviewer_id = null, HR assign manual via UI |
| Existing completed reviews punya reviewer salah | TIDAK diubah — data historis tetap utuh |
| submitManagerAssessment ownership break existing flow | FE sudah cek reviewer_id, jadi hanya backend hardening |

---

## 9. Validasi Akhir

Setelah semua task selesai:

```bash
# 1. Cek reviewer_rules lengkap
docker compose exec -T web php artisan tinker --execute="echo \App\Models\ReviewerRule::count();"
# Expected: 4

# 2. Cek tidak ada self-review
docker compose exec -T web php artisan tinker --execute="echo \App\Models\PerformanceReview::whereColumn('reviewer_id','staff_member_id')->count();"
# Expected: 0

# 3. Cek semua test pass
cd team-sync-be && php -d memory_limit=2G ./vendor/bin/pest --exclude-filter="PayrollExportTest"
# Expected: all pass

# 4. Cek FE test pass
cd team-sync-fe && bun run test --run
# Expected: all pass
```
