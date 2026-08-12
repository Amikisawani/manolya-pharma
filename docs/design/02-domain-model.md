# 02 — Domain Model

## 1. Bounded contexts

| Context | Responsabilité | Agrégats racines |
|---------|----------------|------------------|
| Identity & Access | Users, rôles, sessions, 2FA | `User`, `Role`, `Session` |
| Tenancy | Isolation SaaS, sites, entrepôts | `Tenant`, `Site`, `Warehouse` |
| Catalog | Produits, catégories, codes | `Product`, `Category` |
| Inventory | Lots, mouvements, seuils | `Batch`, `StockMovement`, `StockAdjustment` |
| Purchasing | Fournisseurs, DA, BC, réceptions | `Supplier`, `PurchaseOrder`, `GoodsReceipt` |
| Sales | POS, tickets, retours | `Sale`, `SaleReturn` |
| Finance | Revenus, dépenses, paiements | `Payment`, `Expense`, `LedgerEntry` |
| InventoryCount | Inventaires physiques | `StockCount` |
| Documents | GED | `Document`, `DocumentVersion` |
| Audit | Journal immuable | `AuditRecord` (append-only) |
| Alerts | Règles & notifications | `Alert`, `AlertRule` |
| Reporting | Snapshots & PDF | `ReportRun` |

## 2. Entités & value objects clés

### Tenancy
- **Tenant** : name, slug, default_currency, timezone, locale, status
- **Site** : pharmacy branch under tenant
- **Warehouse** : stock location under site

### Catalog — Product
- sku/reference, commercial_name, generic_name, category_id
- barcode, qr_payload, manufacturer, preferred_supplier_id
- purchase_price (tarif ref), sale_price, min_stock, critical_stock
- description, image_path, allocation_strategy (`fefo`|`fifo`)
- VO : `Money`, `Barcode`, `Sku`

### Inventory — Batch
- product_id, warehouse_id, lot_number
- manufactured_at, expires_at
- quantity_on_hand, unit_cost
- status: `active`|`quarantine`|`expired`|`depleted`
- Invariants : qty ≥ 0 ; expired ⇒ non vendable

### Inventory — StockMovement (immuable)
- type: `IN_PURCHASE`|`IN_RETURN`|`IN_ADJUSTMENT`|`OUT_SALE`|`OUT_RETURN_SUPPLIER`|`OUT_ADJUSTMENT`|`OUT_EXPIRED`|`TRANSFER`
- batch_id, quantity, unit_cost, reference_type/id, user_id, occurred_at
- Jamais update/delete

### Sales — Sale
- number, site_id, cashier_id, status (`draft`|`completed`|`voided`)
- lines[] : product_id, batch_id, qty, unit_price_snapshot, discount, cost_snapshot
- payments[] : method, amount, gateway_ref
- totals : subtotal, discount_total, tax_total, grand_total, cost_total, profit
- Invariant : completed ⇒ mouvements stock créés atomiquement

### Purchasing — PurchaseOrder
- status: `draft`|`pending_approval`|`approved`|`partially_received`|`received`|`cancelled`
- lines, supplier_id, approvals[]

### StockCount
- type: `full`|`rotating`|`partial`
- status: `open`|`counting`|`review`|`validated`|`cancelled`
- lines: expected_qty, counted_qty, variance

## 3. Invariants transverses

1. Toute entité métier porte `tenant_id`.
2. Soft-delete : `deleted_at`, `deleted_by`, `delete_reason`.
3. Money : montant + `currency_code` cohérent avec tenant (V1 une devise active).
4. FEFO : allocation vente trie `expires_at ASC` parmi lots `active` non expirés.
5. Marge ligne = (unit_price_snapshot - cost_snapshot) * qty - discount.

## 4. Domain services

- `BatchAllocator` — sélection lots FEFO/FIFO
- `StockMutator` — applique mouvement + maj qty batch (transaction)
- `PricingService` — marge, validation remises
- `ExpiryPolicy` — bloque vente / marque expired
- `ApprovalPolicy` — seuils validation achats/ajustements

## 5. Anti-corruption / ports (V2 IA)

```
ForecastingPort::predictDemand(productId, horizon): DemandForecast
AnomalyPort::detect(salesWindow): Anomaly[]
ReplenishmentPort::suggest(warehouseId): PurchaseSuggestion[]
```

Adapters V1 = no-op ou règles heuristiques simples (stock min), sans ML.

## 6. Context map (relations)

- Sales **ACL** → Inventory (`BatchAllocator`, `StockMutator`)
- Purchasing **ACL** → Inventory (création batches)
- Sales / Purchasing → Finance (payments, expenses)
- Tous contextes critiques → Audit (events)
- Inventory / Sales / Identity → Alerts
- Finance + Inventory → Reporting
