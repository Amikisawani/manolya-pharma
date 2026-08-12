# 08 — Diagrammes UML

## 1. Context map

```mermaid
flowchart LR
  Identity[Identity_Access]
  Tenancy[Tenancy]
  Catalog[Catalog]
  Inventory[Inventory]
  Purchasing[Purchasing]
  Sales[Sales_POS]
  Finance[Finance]
  Counts[StockCounts]
  Docs[Documents]
  Audit[Audit]
  Alerts[Alerts]
  Reporting[Reporting]

  Tenancy --> Identity
  Catalog --> Inventory
  Purchasing --> Inventory
  Sales --> Inventory
  Sales --> Finance
  Purchasing --> Finance
  Counts --> Inventory
  Docs --> Finance
  Identity --> Audit
  Sales --> Audit
  Inventory --> Alerts
  Reporting --> Finance
  Reporting --> Inventory
```

## 2. Séquence — Vente POS

```mermaid
sequenceDiagram
  participant Cashier
  participant POS as PosController
  participant SaleSvc as CompleteSaleService
  participant Alloc as BatchAllocator
  participant Stock as StockMutator
  participant Pay as PaymentGateway
  participant Audit as AuditLogger

  Cashier->>POS: CompleteSale request
  POS->>SaleSvc: execute DTO
  SaleSvc->>Alloc: allocate FEFO lines
  Alloc-->>SaleSvc: batch allocations
  SaleSvc->>Pay: charge payments
  Pay-->>SaleSvc: PaymentResult OK
  SaleSvc->>Stock: decrease per allocation
  SaleSvc->>Audit: record SaleCompleted
  SaleSvc-->>POS: Sale + ticket
  POS-->>Cashier: Inertia redirect ticket
```

## 3. États — Bon de commande

```mermaid
stateDiagram-v2
  [*] --> draft
  draft --> pending_approval: submit
  pending_approval --> approved: approve
  pending_approval --> rejected: reject
  rejected --> draft: revise
  approved --> partially_received: receive_partial
  approved --> received: receive_full
  partially_received --> received: receive_remaining
  draft --> cancelled: cancel
  pending_approval --> cancelled: cancel
  approved --> cancelled: cancel_if_not_received
```

## 4. États — Inventaire physique

```mermaid
stateDiagram-v2
  [*] --> open
  open --> counting: start
  counting --> review: submit_counts
  review --> counting: rework
  review --> validated: validate
  open --> cancelled: cancel
  counting --> cancelled: cancel
```

## 5. États — Vente

```mermaid
stateDiagram-v2
  [*] --> draft
  draft --> completed: capture_and_commit
  draft --> voided: abandon
  completed --> refunded: refund_partial_or_full
```

## 6. Classes — Inventory (simplifié)

```mermaid
classDiagram
  class Product {
    UUID id
    String sku
    Money salePrice
    Strategy allocation
  }
  class Batch {
    UUID id
    String lotNumber
    Date expiresAt
    Decimal qty
    Money unitCost
  }
  class StockMovement {
    <<immutable>>
    String type
    Decimal quantity
    DateTime occurredAt
  }
  Product "1" --> "*" Batch
  Batch "1" --> "*" StockMovement
```
