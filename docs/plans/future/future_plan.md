# Future Plans — Team Sync SaaS

> Rencana bisnis, monetisasi, dan infrastruktur SaaS.
> Dipindahkan dari PRD agar PRD fokus ke aplikasi utama.

---

## 1. Business Model

### Pricing Tiers

| Fitur | Free | Pro Monthly | Pro Yearly | Lifetime |
|-------|------|-------------|------------|----------|
| **Harga** | Rp 0 | Rp 299.000/bulan | Rp 2.999.000/tahun | Rp 14.999.000 |
| **Hosting** | Cloud only | Cloud atau Self-hosted | Cloud atau Self-hosted | Self-hosted only |
| **Domain** | Subdomain only | Subdomain atau Custom | Subdomain atau Custom | Custom only |
| Max Karyawan | 10 | 200 | 200 | Unlimited |
| Max Teams | 1 | Unlimited | Unlimited | Unlimited |
| Free Trial Pro | 30 hari | — | — | — |
| Staff Management | ✅ | ✅ | ✅ | ✅ |
| Attendance (Basic) | ✅ | ✅ | ✅ | ✅ |
| Teams | ✅ (max 1) | ✅ | ✅ | ✅ |
| Payroll + THR | ❌ | ✅ | ✅ | ✅ |
| Analytics | ❌ | ✅ | ✅ | ✅ |
| Performance Review + TOPSIS | ❌ | ✅ | ✅ | ✅ |
| Projects & Tasks | ❌ | ✅ | ✅ | ✅ |
| Overtime Management | ❌ | ✅ | ✅ | ✅ |
| Leave Management | ❌ | ✅ | ✅ | ✅ |
| Meetings | ❌ | ✅ | ✅ | ✅ |
| Export Excel/PDF | ❌ | ✅ | ✅ | ✅ |
| Custom Domain | ❌ | ✅ | ✅ | ✅ |
| Maintenance | — | Included | Included | 1 tahun included, opsional setelahnya |
| Support | Community | Priority | Priority | 1 tahun (opsional lanjut) |

### Maintenance Model (Lifetime)
- **Tahun 1**: INCLUDED di harga (support + update)
- **Tahun 2+**: OPTIONAL — Rp 1.999.000/tahun
- Tanpa maintenance: aplikasi tetap jalan, tapi tidak dapat update/support

---

## 2. Hosting & Deployment

### Multi-Instance Architecture

Setiap customer mendapatkan **instance terpisah** (database + container sendiri).

```
┌─────────────────────────────────────────────────────────┐
│              Admin Dashboard (repo terpisah)             │
│  ┌───────────────────────────────────────────────────┐  │
│  │ Monitor semua customer:                            │  │
│  │ • License status & expiry                          │  │
│  │ • Active users per instance                        │  │
│  │ • Revenue & MRR                                    │  │
│  │ • Health check semua instance                      │  │
│  └───────────────────────────────────────────────────┘  │
└────────┬──────────────┬──────────────┬──────────────────┘
         │              │              │
  ┌──────▼───────┐ ┌───▼────────┐ ┌───▼────────┐
  │  Instance A   │ │ Instance B │ │ Instance C │
  │  Docker + DB  │ │ Docker + DB│ │ Docker + DB│
  └───────────────┘ └────────────┘ └────────────┘
```

### Database Strategy
- Shared MySQL server, database-per-customer
- User database credentials per customer (isolasi akses)
- Encrypt sensitive data at rest

### Tooling
| Tool | Fungsi |
|------|--------|
| Docker | Containerization |
| Caddy | Reverse proxy + SSL |
| Coolify/Dokploy | Self-hosted PaaS |
| GitHub Actions | CI/CD |

---

## 3. Payment Integration

### Roadmap
| Phase | Gateway | Status |
|-------|---------|--------|
| 1 | Manual (invoice + bank transfer) | Current |
| 2 | **Midtrans/Xendit** (QRIS, e-wallet, bank transfer) | Next |
| 3 | Stripe (international) | Planned |

### Indonesia Payment Methods
- QRIS (universal)
- E-Wallet: GoPay, OVO, DANA, ShopeePay
- Bank Transfer: BCA, Mandiri, BNI, BRI
- Credit/Debit Card: Visa, Mastercard

---

## 4. License System

### Validation Flow (Self-Hosted)
- License key: signed, tamper-proof
- Cache validity: 24 jam
- Grace period: 72 jam kalau central server unreachable
- Instance binding: tidak bisa dipakai di 2 instance

### Free Trial Flow
1. User daftar → Free tier
2. Claim 30 hari trial Pro (wajib payment method)
3. Setelah 30 hari: auto-charge atau downgrade
4. Reminder: H-7, H-3, H-1

---

## 5. Churn Prevention

| Trigger | Action |
|---------|--------|
| Trial H-7/H-3/H-1 | Reminder email + in-app |
| Trial expired | Downgrade ke Free |
| Pro expired H-7 | Reminder perpanjang |
| Pro expired | Grace period 7 hari |
| User tidak aktif 30 hari | "We miss you" email |
| Win-back (30 hari post-cancel) | Diskon 20% |

---

## 6. Refund Policy

| Scenario | Refund |
|----------|--------|
| Pro Monthly: hari 1-7 | Full refund |
| Pro Monthly: hari 8-30 | Pro-rata |
| Pro Monthly: setelah 30 hari | Tidak ada |
| Pro Yearly: bulan 1 | Full refund (minus admin) |
| Pro Yearly: bulan 2-6 | Pro-rata |
| Pro Yearly: setelah bulan 6 | Tidak ada |
| Lifetime: setelah aktivasi | Tidak ada |
| Lifetime: sebelum aktivasi | Full refund |

---

## 7. Revenue Projection

### Year 1
| Source | Calculation | Revenue |
|--------|------------|---------|
| Pro Monthly (12 months) | 15 × Rp 299rb × 12 | Rp 53.820.000 |
| Pro Yearly | 10 × Rp 2.999rb | Rp 29.990.000 |
| Lifetime | 10 × Rp 14.999rb | Rp 149.990.000 |
| **Total** | | **Rp 233.800.000** |

### Cost Structure
| Cost | Monthly | Annual |
|------|---------|--------|
| Server | Rp 1.000.000 | Rp 12.000.000 |
| Domain & DNS | Rp 100.000 | Rp 1.200.000 |
| Payment fees (~2%) | Rp 300.000 | Rp 3.600.000 |
| Email service | Rp 200.000 | Rp 2.400.000 |
| **Total** | **Rp 1.600.000** | **Rp 19.200.000** |

### Profit (Estimated)
- Revenue: Rp 233.800.000
- Cost: Rp 19.200.000
- **Profit: Rp 214.600.000** (sebelum pajak)

---

## 8. Roadmap

### Phase 2 — SaaS Ready
- [ ] Docker containerization
- [ ] Instance orchestration (Coolify/Dokploy)
- [ ] Subdomain routing (*.teamsync.co)
- [ ] Custom domain support
- [ ] Payment gateway: Midtrans/Xendit
- [ ] Subscription management
- [ ] Free trial flow
- [ ] Invoice/receipt generation
- [ ] Churn prevention automation
- [ ] License validation API

### Phase 3 — Admin Dashboard (Repo Terpisah)
- [ ] Customer management
- [ ] License expiry monitoring
- [ ] Revenue & MRR dashboard
- [ ] Instance health monitoring
- [ ] Billing history
- [ ] Support ticket system

### Phase 4 — Growth
- [ ] Stripe integration
- [ ] Mobile app
- [ ] API marketplace
- [ ] White-label option
- [ ] Advanced reporting & BI

### Phase 5 — Enterprise
- [ ] SSO/LDAP
- [ ] Custom workflows
- [ ] API rate limiting per tier
- [ ] SLA guarantees
- [ ] Dedicated support

---

## 9. Success Metrics

| Metric | Target |
|--------|--------|
| Free → Pro conversion | >5% |
| Monthly active users | >1000 (Year 1) |
| Customer churn (Pro) | <5% monthly |
| NPS Score | >50 |
| TOPSIS adoption | >80% Pro users |
| Uptime | >99.5% |
| MRR | >Rp 50jt (Year 1) |
| Lifetime sales | >50 units (Year 1) |
