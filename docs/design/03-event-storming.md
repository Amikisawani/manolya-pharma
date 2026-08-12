# 03 — Event Storming

## Légende

- **Commande** : intention utilisateur/système
- **Événement** : fait passé (immutable)
- **Politique** : réaction automatique « dès que… alors… »
- **Read model** : vue pour UI / rapports

---

## 1. Identity & Access

| Commande | Événement | Politique |
|----------|-----------|-----------|
| RegisterUser | UserRegistered | Envoyer invitation |
| Login | UserLoggedIn / LoginFailed | Compter échecs → LockAccount si seuil |
| Verify2FA | TwoFactorVerified | Ouvrir session |
| Logout / ForceLogout | UserLoggedOut / SessionRevoked | Invalider token |
| ResetPassword | PasswordResetRequested / PasswordChanged | Invalider autres sessions |

## 2. Catalog

| Commande | Événement | Politique |
|----------|-----------|-----------|
| CreateProduct | ProductCreated | Indexer Meilisearch |
| UpdateProduct | ProductUpdated | Réindexer ; si prix → PriceChanged (audit) |
| SoftDeleteProduct | ProductArchived | Retirer recherche active |
| ImportProducts | ProductImportCompleted | Notifier gestionnaire |

## 3. Inventory

| Commande | Événement | Politique |
|----------|-----------|-----------|
| AllocateForSale | BatchesAllocated | — |
| DecreaseStock | StockDecreased | Si qty ≤ critical → CriticalStockAlertRaised |
| IncreaseStock | StockIncreased | Clear rupture si applicable |
| AdjustStock | StockAdjusted | Audit + si écart > seuil → InventoryVarianceAlert |
| MarkExpired | BatchExpired | Bloquer ventes ; OutExpired movement |
| TransferStock | StockTransferred | Mouvements pair OUT/IN |

**Politique scheduler** : `ScanExpiries` → `ExpiryAlertRaised` / `BatchExpired`

## 4. Purchasing

| Commande | Événement | Politique |
|----------|-----------|-----------|
| CreatePurchaseRequest | PurchaseRequestCreated | Notifier approbateur |
| SubmitPurchaseOrder | PurchaseOrderSubmitted | Workflow approval |
| ApprovePurchaseOrder | PurchaseOrderApproved | Notifier acheteur |
| RejectPurchaseOrder | PurchaseOrderRejected | Notifier auteur |
| ReceiveGoods | GoodsReceived | Create/update batches + StockIncreased |
| RecordSupplierInvoice | SupplierInvoiceRecorded | Ledger expense / payable |
| PaySupplier | SupplierPaid | Maj solde |

## 5. Sales / POS

| Commande | Événement | Politique |
|----------|-----------|-----------|
| StartSale | SaleStarted | — |
| AddSaleLine | SaleLineAdded | Allocate batch |
| ApplyDiscount | DiscountApplied | Vérifier permission/seuil |
| CapturePayment | PaymentCaptured / PaymentFailed | Retry policy gateway |
| CompleteSale | SaleCompleted | StockDecreased, LedgerRevenue, TicketGenerated, Audit |
| VoidSale | SaleVoided | Reverse stock si applicable (policy) |
| RefundSale | SaleRefunded | Stock IN_RETURN optionnel + paiement reverse |

## 6. Stock count

| Commande | Événement | Politique |
|----------|-----------|-----------|
| OpenStockCount | StockCountOpened | — |
| RecordCountLine | StockCountLineRecorded | — |
| SubmitStockCount | StockCountSubmitted | Calcul écarts |
| ValidateStockCount | StockCountValidated | Générer AdjustStock pour écarts |

## 7. Finance / Documents / Alerts / Reporting

| Commande | Événement | Politique |
|----------|-----------|-----------|
| RecordExpense | ExpenseRecorded | Audit |
| UploadDocument | DocumentUploaded | Job OCR |
| CompleteOcr | DocumentOcrCompleted | Index full-text |
| RaiseAlert | AlertRaised | Notify in-app + email (+ SMS port) |
| AcknowledgeAlert | AlertAcknowledged | — |
| GenerateDailyReport | DailyReportGenerated | EmailOwner |
| GenerateMonthlyReport | MonthlyReportGenerated | EmailOwner |

## 8. Chaînes critiques (happy paths)

### Vente
```
CompleteSale
  → SaleCompleted
  → StockDecreased (par ligne/lot)
  → PaymentCaptured
  → LedgerRevenueRecorded
  → TicketGenerated
  → AuditRecorded
  → (si seuil) CriticalStockAlertRaised
```

### Réception
```
ReceiveGoods
  → GoodsReceived
  → BatchCreated / BatchQuantityIncreased
  → StockIncreased
  → AuditRecorded
```

### Rapport journalier
```
Scheduler 23:59
  → GenerateDailyReport
  → DailyReportGenerated
  → OwnerDailyReportSent
```

## 9. Read models

- ExecutiveDashboard (CA j/m, profit, ruptures, expirations, top produits)
- PosProductSearch (Meilisearch)
- AuditTimeline
- StockExpiryBoard
- SupplierBalance
- AlertInbox
