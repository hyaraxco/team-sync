# TEA-6: Use accepted NIK or NPWP status before applying PPh 21 higher rate

**Status**: COMPLETED
**Bead**: team-sync-87i

## Problem

`PayrollTaxPeriodService` and `ThrService` derive `hasNpwp` from only `filled($employee->npwp)` / `! empty($employee->npwp)`. An employee with an accepted integrated NIK (per PENG-6/PJ.09/2024) but no legacy NPWP gets the +20% PPh 21 surcharge incorrectly.

## Authority

DJP PENG-6/PJ.09/2024 points 7-8: integrated Dukcapil/DJP NIK identities are excluded from the higher Article 21(5a) rate. No real DJP integration — acceptance is owner-controlled.

## Design

### New columns on `staff_member_profiles`

- `nik_taxpayer_accepted_hash` — `varchar(64)`, nullable. Stores the `identity_number_hash` at time of acceptance. Null = not accepted.
- `nik_taxpayer_accepted_at` — `timestamp`, nullable. When acceptance was recorded.
- `nik_taxpayer_accepted_by` — `unsigned bigint`, nullable, FK to `users.id`. Which Finance user accepted.

No boolean flag: acceptance is bound to a specific identity hash. If the NIK changes (new hash), `hasTaxpayerId()` sees a mismatch and returns false. No explicit invalidation logic needed in model hooks or update paths.

### Model method

```php
public function hasTaxpayerId(): bool
{
    if (filled($this->npwp)) {
        return true;
    }

    return $this->nik_taxpayer_accepted_hash !== null
        && $this->nik_taxpayer_accepted_hash === $this->identity_number_hash;
}
```

Requires both: (1) an acceptance was recorded, and (2) the accepted identity is still the current one.

### Invalidation

Automatic: `identity_number_hash` is recomputed on every save via the existing `booted()` hook. If the NIK changes, the hash changes, and the stale `nik_taxpayer_accepted_hash` no longer matches — `hasTaxpayerId()` returns false without touching any update path.

Clearing NPWP also works: the `filled($this->npwp)` branch fails, and the hash check governs.

### Dedicated Finance acceptance endpoint

`POST /api/v1/staff-members/{id}/accept-nik-taxpayer` — guarded by `payroll-process` permission (Finance).

- Validates employee exists and has a non-empty `identity_number_hash`
- Stamps `nik_taxpayer_accepted_hash`, `nik_taxpayer_accepted_at`, `nik_taxpayer_accepted_by`
- Returns confirmation with `accepted_at` timestamp (no hash exposed — hash is internal audit data)

Not wired into generic profile update. HR `staff-member-edit` cannot set this — Finance owns the tax decision.

### Caller changes

| File | Old | New |
|------|-----|-----|
| `PayrollTaxPeriodService:310,313,320` | `filled($employee->npwp)` | `$employee->hasTaxpayerId()` |
| `ThrService:115` | `! empty($employee->npwp)` | `$employee->hasTaxpayerId()` |

### Exposed via API

- `StaffMemberProfileResource`: expose `nik_taxpayer_accepted` (boolean, hash-equality check) and `nik_taxpayer_accepted_at` (sensitive guard)
- No changes to `StaffMemberProfileStoreRequest` or `StaffMemberProfileUpdateRequest` — not a profile field

### Test

Pest test covering:
- `hasTaxpayerId()` → true when NPWP filled (regardless of NIK acceptance)
- `hasTaxpayerId()` → true when no NPWP but NIK accepted with matching hash
- `hasTaxpayerId()` → false when no NPWP and no acceptance
- `hasTaxpayerId()` → false when accepted hash doesn't match current hash (NIK changed)
- Endpoint: Finance can accept, HR cannot, acceptance resets on NIK change

## Out of scope

- DJP/Dukcapil integration or automated verification
- Frontend UI for the new field
- Existing `has_npwp` references in tests/models/resources were renamed to `has_taxpayer_id` as part of this change
