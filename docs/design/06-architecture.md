# 06 — Architecture logicielle

## 1. Style

- **DDD** + **Clean Architecture** (Dependency Rule vers le Domain)
- **Event-Driven** (Domain Events → Listeners/Jobs)
- **Modular monolith** Laravel 12 (pas de microservices en V1)
- SOLID, DRY, KISS

## 2. Stack

| Couche | Techno |
|--------|--------|
| Backend | Laravel 12, PHP 8.3+, Sanctum, Policies, Events, Notifications, Queues, Scheduler |
| Async | Redis, Horizon, Reverb |
| DB | PostgreSQL 16 |
| Search | Scout + Meilisearch |
| Frontend | Vue 3, TypeScript, Inertia.js, Tailwind, ShadCN Vue, ApexCharts |
| Ops | Docker Compose, GitHub Actions, Sentry, Coolify/Forge |
| PDF | DomPDF ou Browsershot (choix impl : DomPDF V1) |
| Excel | Maatwebsite Excel |

## 3. Couches applicatives

```
resources/js          → UI Inertia/Vue (présentation)
app/Http              → Controllers, Requests, Middleware, Resources
app/Application       → Application Services, DTOs, UseCases
app/Domain            → Models métier purs, Events, Exceptions, Ports
app/Infrastructure    → Eloquent repos, Gateways MoMo, OCR, Mail, Storage
app/Policies          → Autorisation
routes/               → web.php (Inertia), api.php si besoin
```

### Règle de dépendance
`Http → Application → Domain ← Infrastructure`

Infrastructure implémente les interfaces Domain/Application.

## 4. Multi-tenancy

- Colonne `tenant_id` + Global Scope Eloquent `TenantScope`
- Middleware `EnsureTenant` après auth (résolution via user.tenant_id)
- Jobs : sérialiser `tenant_id` et re-appliquer le scope
- Super Admin : bypass contrôlé + impersonation auditée (Phase 4)

## 5. Sécurité

| Contrôle | Implémentation |
|----------|----------------|
| Auth | Sanctum session SPA / cookies |
| MFA | TOTP (self-hosted) + recovery codes |
| RBAC | roles + permissions + Policies |
| Brute force | Rate limiter + lockout user |
| CSRF | Inertia/Laravel default |
| XSS | Vue escaping + CSP headers |
| SQLi | Eloquent/Query Builder bound |
| Secrets | `.env`, encrypted casts (2FA secret) |
| Audit | table append-only |
| Sessions | revoke / force logout |
| Uploads | mime/size validation, private disk |

## 6. Paiements (port)

```php
interface PaymentGateway {
    public function charge(PaymentIntent $intent): PaymentResult;
    public function refund(string $providerRef, Money $amount): PaymentResult;
}
```
Adapters : `CashGateway`, `CardManualGateway`, `OrangeMoneyGateway`, `AirtelMoneyGateway`, `MtnMomoGateway` (stubs + cash réel en P0).

## 7. Temps réel

- Reverb : alertes, dashboard KPIs légers, statut import
- Channels privés `tenant.{id}.alerts`

## 8. Jobs critiques

- `ImportProductsJob`
- `ScanBatchExpiriesJob` (daily)
- `DetectStockThresholdsJob` (hourly)
- `GenerateDailyReportJob` (23:59 tenant TZ)
- `GenerateMonthlyReportJob`
- `ProcessDocumentOcrJob`
- `SendAlertNotificationJob`

## 9. Observabilité

- Sentry (PHP + Vue)
- Horizon dashboard (accès Super Admin)
- Structured logging JSON
- Healthchecks `/up`

## 10. Déploiement

Docker Compose local : `app`, `postgres`, `redis`, `meilisearch`, `mailpit`, `horizon`, `reverb`, `scheduler`.  
Prod : Coolify ou Forge — images identiques, secrets runtime.
