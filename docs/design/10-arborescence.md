# 10 — Arborescence projet

```
manolya/
├── docs/
│   └── design/                 # Dossier conception (Phase 0)
├── docker/
│   ├── php/Dockerfile
│   ├── nginx/default.conf
│   └── postgres/init.sql
├── docker-compose.yml
├── .github/workflows/ci.yml
├── app/
│   ├── Domain/
│   │   ├── Shared/             # Money, Uuid, TenantId, DomainEvent
│   │   ├── Identity/
│   │   ├── Tenancy/
│   │   ├── Catalog/
│   │   ├── Inventory/
│   │   ├── Purchasing/
│   │   ├── Sales/
│   │   ├── Finance/
│   │   ├── Documents/
│   │   ├── Audit/
│   │   ├── Alerts/
│   │   ├── Reporting/
│   │   └── Ai/                 # Ports V2 only
│   ├── Application/
│   │   ├── Identity/
│   │   ├── Catalog/
│   │   ├── Inventory/
│   │   ├── Purchasing/
│   │   ├── Sales/
│   │   ├── Finance/
│   │   ├── Documents/
│   │   ├── Audit/
│   │   ├── Alerts/
│   │   └── Reporting/
│   ├── Infrastructure/
│   │   ├── Persistence/Eloquent/
│   │   ├── Search/
│   │   ├── Payments/
│   │   ├── Ocr/
│   │   ├── Sms/
│   │   └── Ai/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   ├── Policies/
│   ├── Jobs/
│   ├── Listeners/
│   ├── Notifications/
│   ├── Models/                 # Eloquent models (infra bridge)
│   └── Providers/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── js/
│   │   ├── Components/ui/      # ShadCN
│   │   ├── Layouts/
│   │   ├── Pages/
│   │   │   ├── Auth/
│   │   │   ├── Dashboard/
│   │   │   ├── Pos/
│   │   │   ├── Catalog/
│   │   │   ├── Stock/
│   │   │   ├── Purchasing/
│   │   │   ├── InventoryCounts/
│   │   │   ├── Finance/
│   │   │   ├── Documents/
│   │   │   ├── Audit/
│   │   │   └── Settings/
│   │   ├── Composables/
│   │   ├── Types/
│   │   └── app.ts
│   ├── css/app.css
│   └── views/app.blade.php
├── routes/
│   ├── web.php
│   ├── console.php
│   └── channels.php
├── tests/
│   ├── Unit/
│   ├── Application/
│   └── Feature/
├── composer.json
├── package.json
└── README.md
```
