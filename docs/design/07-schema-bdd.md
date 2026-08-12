# 07 — Schéma base de données (PostgreSQL)

Conventions : UUID `id` PK, `tenant_id` UUID NOT NULL (sauf table `tenants`), `created_at`/`updated_at`, soft delete `deleted_at`/`deleted_by`/`delete_reason`, montants `NUMERIC(18,2)`, devises `CHAR(3)`.

---

## Tenancy & Identity

### tenants
`id`, `name`, `slug` UNIQUE, `default_currency`, `timezone`, `locale`, `status`, timestamps, soft delete

### sites
`id`, `tenant_id`, `name`, `code`, `address`, `is_main`, timestamps, soft delete

### warehouses
`id`, `tenant_id`, `site_id`, `name`, `code`, `is_default`, timestamps, soft delete

### users
`id`, `tenant_id` NULLABLE (super admin), `site_id` NULLABLE, `name`, `email` UNIQUE per tenant, `password`, `phone`, `is_active`, `two_factor_secret` encrypted, `two_factor_recovery_codes` encrypted, `two_factor_confirmed_at`, `locked_until`, `failed_login_attempts`, timestamps, soft delete

### roles / permissions / role_permission / model_has_roles
Spatie-like ou tables custom : `roles(name, tenant_id NULL for system)`, `permissions(name)`, pivots

### login_histories
`id`, `user_id`, `tenant_id`, `ip`, `user_agent`, `success`, `failure_reason`, `created_at`

### sessions_registry (app-level, en plus sessions Laravel)
`id`, `user_id`, `tenant_id`, `ip`, `user_agent`, `last_activity_at`, `revoked_at`

---

## Catalog

### categories
`id`, `tenant_id`, `name`, `parent_id`, timestamps, soft delete

### products
`id`, `tenant_id`, `category_id`, `sku`, `commercial_name`, `generic_name`, `barcode`, `qr_payload`, `manufacturer`, `preferred_supplier_id`, `purchase_price`, `sale_price`, `currency_code`, `min_stock`, `critical_stock`, `allocation_strategy` (`fefo`|`fifo`), `description`, `image_path`, timestamps, soft delete  
UNIQUE(`tenant_id`,`sku`), INDEX barcode

---

## Inventory

### batches
`id`, `tenant_id`, `product_id`, `warehouse_id`, `lot_number`, `manufactured_at`, `expires_at`, `quantity_on_hand`, `unit_cost`, `currency_code`, `status`, timestamps, soft delete  
UNIQUE(`tenant_id`,`product_id`,`warehouse_id`,`lot_number`)

### stock_movements (immutable — no updated_at delete)
`id`, `tenant_id`, `batch_id`, `product_id`, `warehouse_id`, `type`, `quantity`, `unit_cost`, `reference_type`, `reference_id`, `user_id`, `notes`, `occurred_at`, `created_at`

### stock_adjustments
`id`, `tenant_id`, `batch_id`, `quantity_delta`, `reason`, `status`, `requested_by`, `approved_by`, `approved_at`, timestamps, soft delete

---

## Purchasing

### suppliers
`id`, `tenant_id`, `name`, `code`, `phone`, `email`, `address`, `payment_terms`, timestamps, soft delete

### purchase_orders
`id`, `tenant_id`, `site_id`, `supplier_id`, `number`, `status`, `ordered_at`, `expected_at`, `subtotal`, `total`, `currency_code`, `created_by`, `approved_by`, `approved_at`, timestamps, soft delete

### purchase_order_lines
`id`, `tenant_id`, `purchase_order_id`, `product_id`, `quantity_ordered`, `quantity_received`, `unit_cost`, timestamps

### goods_receipts
`id`, `tenant_id`, `purchase_order_id`, `number`, `received_by`, `received_at`, timestamps, soft delete

### goods_receipt_lines
`id`, `tenant_id`, `goods_receipt_id`, `product_id`, `batch_id`, `lot_number`, `expires_at`, `quantity`, `unit_cost`

### supplier_invoices / supplier_payments
facture : `number`, `amount`, `status`, `due_at`  
paiement : `method`, `amount`, `paid_at`, `reference`

---

## Sales

### sales
`id`, `tenant_id`, `site_id`, `warehouse_id`, `number`, `cashier_id`, `status`, `subtotal`, `discount_total`, `grand_total`, `cost_total`, `profit_total`, `currency_code`, `completed_at`, timestamps, soft delete

### sale_lines
`id`, `tenant_id`, `sale_id`, `product_id`, `batch_id`, `quantity`, `unit_price`, `unit_cost`, `discount_amount`, `line_total`

### sale_payments
`id`, `tenant_id`, `sale_id`, `method` (`cash`|`card`|`mobile_money`), `provider`, `amount`, `provider_ref`, `status`, `paid_at`

### sale_returns / sale_return_lines
lien `sale_id`, restock flag, refund amounts

---

## Stock counts

### stock_counts
`id`, `tenant_id`, `warehouse_id`, `type`, `status`, `started_by`, `validated_by`, `validated_at`, timestamps

### stock_count_lines
`id`, `stock_count_id`, `product_id`, `batch_id`, `expected_qty`, `counted_qty`, `variance`

---

## Finance

### expenses
`id`, `tenant_id`, `category`, `amount`, `currency_code`, `spent_at`, `description`, `document_id`, `recorded_by`, timestamps, soft delete

### ledger_entries (optional simplified)
`id`, `tenant_id`, `direction` (`credit`|`debit`), `amount`, `account_code`, `reference_type/id`, `occurred_at`

---

## Documents / Alerts / Audit / Reports

### documents
`id`, `tenant_id`, `type`, `title`, `current_version`, `search_text`, timestamps, soft delete

### document_versions
`id`, `document_id`, `version`, `disk_path`, `mime`, `size`, `ocr_status`, `uploaded_by`, `created_at`

### alerts
`id`, `tenant_id`, `type`, `severity`, `title`, `body`, `reference_type/id`, `status` (`open`|`acked`|`resolved`), `raised_at`, `acked_by`

### audit_records (append-only)
`id`, `tenant_id`, `user_id`, `action`, `auditable_type`, `auditable_id`, `old_values` JSONB, `new_values` JSONB, `ip`, `user_agent`, `created_at`  
Indexes : (tenant_id, created_at), (auditable_type, auditable_id)

### report_runs
`id`, `tenant_id`, `type` (`daily`|`monthly`), `period_start`, `period_end`, `disk_path`, `status`, `sent_at`, `created_at`

---

## ERD simplifié

```
tenants 1─* sites 1─* warehouses
tenants 1─* users
tenants 1─* products 1─* batches
batches 1─* stock_movements
sales 1─* sale_lines → batches
purchase_orders 1─* goods_receipts → batches
```
