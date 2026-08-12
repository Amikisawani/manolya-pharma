# 14 — Roadmap V1 / V2

## Phase 1 — Foundation
Scaffold Laravel 12 + Inertia Vue3 TS Tailwind ShadCN, Docker Compose, Sanctum, 2FA, login history, sessions revoke, TenantScope, Policies skeleton, AuditLogger, Scout/Meilisearch, seed rôles.

**Done when** : login+2FA, user/role seed, audit login, docker up healthy.

## Phase 2 — MVP métier
Products/batches, stock movements FEFO, POS payments abstrait, suppliers+PO+receiving, executive dashboard ApexCharts, alerts rupture/expiry, audit timeline.

**Done when** : parcours achat→réception→vente→dashboard sur données seed Congo.

## Phase 3 — Avancé
Stock counts, finance expenses/margins, GED+OCR job, daily/monthly PDF emails, Excel import/export.

**Done when** : inventaire validé génère ajustements ; PDF journalier job testable.

## Phase 4 — SaaS durci
Multi-sites/warehouses UI, SmsGateway, Sentry, GitHub Actions CI, backups docs, perf POS, Ai ports wired to heuristics.

**Done when** : CI green, Sentry hooked, second site isolé.

## V2 — IA décisionnelle
Forecasting, anomaly detection, replenishment suggestions, profitability analysis — replace heuristic adapters with real models.
