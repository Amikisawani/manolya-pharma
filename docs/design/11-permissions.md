# 11 — Permissions RBAC

## Rôles système

| Rôle | Code | Scope |
|------|------|-------|
| Super Admin | `super_admin` | Plateforme (tous tenants) |
| Propriétaire | `owner` | Tenant |
| Pharmacien | `pharmacist` | Tenant / site |
| Gestionnaire de Stock | `stock_manager` | Tenant / site |
| Caissier | `cashier` | Site |
| Comptable | `accountant` | Tenant |
| Auditeur | `auditor` | Tenant (read) |

## Catalogue de permissions (extrait normatif)

Préfixe.ressource.action

- `dashboard.view`
- `products.view|create|update|delete|import|export`
- `batches.view|create|update|adjust`
- `suppliers.view|create|update|delete`
- `purchase_orders.view|create|submit|approve|receive|pay`
- `sales.pos|view|discount|refund|void`
- `stock_counts.view|create|count|validate`
- `expenses.view|create|update`
- `finance.reports.view`
- `documents.view|upload|delete`
- `alerts.view|ack`
- `audit.view|export`
- `users.view|create|update|deactivate|force_logout`
- `roles.manage`
- `sites.manage`
- `reports.daily.view|monthly.view`
- `settings.manage`
- `tenants.manage` (super admin)

## Matrice (V = view/use, C = create/mutate, A = approve, X = full, R = read-only)

| Permission | Super | Owner | Pharm | Stock | Cash | Acct | Audit |
|------------|-------|-------|-------|-------|------|------|-------|
| dashboard.view | X | X | V | V | V* | V | V |
| products.* | X | X | X | X | V | V | R |
| batches.adjust | X | X | A | C | — | — | R |
| purchase_orders.approve | X | X | A | — | — | — | R |
| purchase_orders.receive | X | X | C | C | — | — | R |
| sales.pos | X | X | X | — | X | — | — |
| sales.discount | X | X | X | — | limited | — | — |
| sales.refund | X | X | X | — | — | — | — |
| sales.void | X | X | X | — | — | — | — |
| stock_counts.validate | X | X | A | C** | — | — | R |
| expenses.* | X | X | — | — | — | X | R |
| documents.* | X | X | V | V | — | X | R |
| audit.view/export | X | X | V | — | — | V | X |
| users.* | X | X | — | — | — | — | R |
| tenants.manage | X | — | — | — | — | — | — |

\* Caissier : KPIs caisse limités (CA session), pas dashboard exécutif complet  
\*\* Stock manager crée/compte ; validation pharmacien/owner

## Règles

1. Toute route Inertia/API protégée par middleware auth + permission/policy.
2. Remise caissier plafonnée (`settings.max_cashier_discount_percent`).
3. Auditeur : aucune Policy `create/update/delete` métier.
4. Super Admin actions cross-tenant toujours auditées.
