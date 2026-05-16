# Hosting & Deployment — Team Sync SaaS

> Multi-tenant architecture (shared app, database-per-customer), dan phased rollout.

---

## Multi-Tenant Architecture

Setiap customer mendapatkan **database terpisah** di shared Laravel app instance. Model ini sejalan dengan kompetitor (Talenta, Gadjian, greytHR) — cost-efficient, single deployment, update sekali untuk semua customer.

```
┌─────────────────────────────────────────────────────────┐
│              Admin Dashboard (Phase 2C)                   │
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
  │  Customer A   │ │ Customer B │ │ Customer C │
  │  DB: cust_a   │ │ DB: cust_b │ │ DB: cust_c │
  └───────────────┘ └────────────┘ └────────────┘
```

---

## Database Strategy

- Shared MySQL server, database-per-customer
- User database credentials per customer (isolasi akses)
- Encrypt sensitive data at rest
- Tenant resolved via subdomain middleware

---

## Tenant Isolation Concerns (Shared App)

Karena satu Laravel app serve semua customer, perlu isolasi di layer berikut:

| Layer | Concern | Solusi |
|-------|---------|--------|
| **Database** | Cross-tenant query | DB credentials per-tenant, middleware auto-switch connection |
| **Queue** | Job dari tenant A jalan di context tenant B | Prefix queue name per-tenant, atau tenant_id di job payload + middleware |
| **Cache** | Key collision antar tenant | Cache prefix per-tenant (`cache.prefix = tenant_{id}`) |
| **Storage** | File upload tercampur | Disk path per-tenant (`storage/{tenant_id}/`) |
| **Session** | Session hijack cross-tenant | Session scoped ke subdomain (cookie domain) |
| **Scheduler** | Cron job harus run per-tenant | Loop tenants di scheduled command, atau tenant-aware scheduler |

### Recommended Package

- **stancl/tenancy** — Laravel multi-tenancy package, handles DB switching, cache prefix, queue isolation, storage separation out of the box
- Alternatif: manual tenant middleware + config switching (lebih ringan, tapi lebih banyak custom code)

---

## Phase 2A — Revenue First (4 weeks)

**Goal**: First 5 paying customers, manual process, prove demand.

- [ ] Subdomain routing (*.teamsync.co)
- [ ] Tenant resolution middleware (subdomain → database)
- [ ] Manual tenant provisioning (create DB, run migrations, configure .env)
- [ ] Manual invoicing (bank transfer)
- [ ] Basic tenant management commands (artisan)
- [ ] Operational runbook (see below)

### Operational Runbook — Manual Tenant Onboarding

**Steps to onboard new customer:**

1. **Create database**
   ```bash
   mysql -e "CREATE DATABASE teamsync_{customer_slug};"
   mysql -e "CREATE USER '{customer_slug}'@'%' IDENTIFIED BY '{secure_password}';"
   mysql -e "GRANT ALL PRIVILEGES ON teamsync_{customer_slug}.* TO '{customer_slug}'@'%';"
   ```

2. **Run migrations**
   ```bash
   php artisan tenants:migrate --database=teamsync_{customer_slug}
   ```

3. **Create admin user**
   ```bash
   php artisan tenants:seed-admin --database=teamsync_{customer_slug} --email=admin@{customer}.co
   ```

4. **Configure DNS**
   - Add CNAME record: `{customer}.teamsync.co` → `server.teamsync.co`
   - Wait propagation (5-15 menit)

5. **Verify**
   - Visit `https://{customer}.teamsync.co`
   - Login with admin credentials
   - Confirm all modules working

6. **Send credentials**
   - Email admin login + password reset link
   - Include getting started guide

---

## Phase 2B — Automate (6 weeks)

**Goal**: Self-service signup, automated billing, payment gateway.

- [ ] Payment gateway: Midtrans/Xendit (QRIS, e-wallet, bank transfer)
- [ ] Subscription management (create, upgrade, cancel)
- [ ] Auto-billing & invoice generation
- [ ] Free trial flow (14 hari, tanpa kartu)
- [ ] Self-service signup (auto-provision database)
- [ ] Churn prevention automation

---

## Phase 2C — Scale (8 weeks)

**Goal**: Containerization, custom domains, license system, admin dashboard, monitoring.

- [ ] Docker containerization
- [ ] Instance orchestration (Coolify/Dokploy)
- [ ] Custom domain support (Caddy auto-SSL)
- [ ] License system (validation, binding, grace period)
- [ ] Admin dashboard (basic — customer list, health, revenue)
- [ ] Centralized monitoring & alerting

### Monitoring & Alerting

| Metric | Tool | Alert Threshold |
|--------|------|-----------------|
| App errors (5xx) | Sentry / Laravel Telescope | >10/menit |
| Response time (p95) | Uptime Kuma / Prometheus | >2s |
| Queue backlog | Laravel Horizon dashboard | >100 pending jobs |
| Disk usage | Node exporter | >80% |
| DB connections | MySQL metrics | >80% max_connections |
| SSL expiry | Uptime Kuma | <7 hari |
| Uptime per-tenant | Uptime Kuma (per subdomain) | <99.5% |

**Stack rekomendasi (self-hosted, low-cost):**
- **Sentry** (self-hosted) — error tracking per-tenant
- **Uptime Kuma** — uptime monitoring per subdomain
- **Grafana + Prometheus** — metrics dashboard (opsional Phase 3)
- **Alert channel**: Telegram bot / Discord webhook

---

## Tooling

| Tool | Fungsi | Phase |
|------|--------|-------|
| MySQL | Database-per-customer | 2A |
| Caddy | Reverse proxy + SSL | 2A |
| stancl/tenancy | Multi-tenancy (DB, cache, queue, storage) | 2A |
| Midtrans/Xendit | Payment gateway | 2B |
| Docker | Containerization | 2C |
| Coolify/Dokploy | Self-hosted PaaS | 2C |
| Sentry | Error tracking | 2C |
| Uptime Kuma | Uptime monitoring | 2C |
| GitHub Actions | CI/CD | All |
