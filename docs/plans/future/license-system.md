# License System — Team Sync SaaS

> License validation, free trial flow, dan instance binding.
> **Phase**: 2C (Scale) — deferred sampai self-hosted tier launch.

---

## Validation Flow (Self-Hosted)

- License key: signed, tamper-proof
- Cache validity: 24 jam
- Grace period: 72 jam kalau central server unreachable
- Instance binding: tidak bisa dipakai di 2 instance

---

## Free Trial Flow

1. User daftar → Free tier
2. Claim 14 hari trial Pro (tanpa kartu pembayaran)
3. Setelah 14 hari: downgrade ke Free
4. Reminder: H-3, H-1

> Trial tanpa payment method supaya barrier to entry rendah. User experience value dulu sebelum commit.

---

## Phase 2C Checklist

- [ ] License validation API
- [ ] License key generation (signed JWT or similar)
- [ ] Instance binding mechanism
- [ ] Grace period handling
- [ ] Trial → paid upgrade prompt (no auto-charge)
