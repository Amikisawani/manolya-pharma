# 12 — Rapports & Alertes

## Alertes

| Type | Déclencheur | Sévérité | Canaux V1 |
|------|-------------|----------|-----------|
| `stock_critical` | qty ≤ critical_stock | high | in-app, email |
| `stock_out` | qty = 0 | critical | in-app, email |
| `expiry_soon` | expires_at ≤ J+N (config) | medium/high | in-app, email |
| `expired` | expires_at &lt; today | critical | in-app, email |
| `overstock` | qty ≫ max (config) | low | in-app |
| `dormant` | no movement ≥ N days | low | in-app |
| `inventory_variance` | \|variance\| &gt; seuil | high | in-app, email |
| `abnormal_sale` | qty/montant anormal | high | in-app, email |
| `suspicious_login` | geo/IP/lockout | critical | in-app, email |

SMS : interface `SmsGateway` + adapter null/log en V1 ; enable Phase 4.

### Déduplication
Clé `tenant + type + reference` : pas de spam si alerte `open` existe déjà.

## Rapport journalier PDF (23:59 TZ tenant)

Contenu :
- En-tête Manolya + pharmacie + date
- Résumé exécutif (5 lignes auto)
- CA du jour, profit du jour, dépenses du jour
- Nb ventes, panier moyen
- Top produits vendus
- Produits critiques / expirés / alertes ouvertes
- Pied de page génération + confidentialité

Job : `GenerateDailyReportJob` → storage privé → `OwnerDailyReportMail`.

## Rapport mensuel PDF (fin de mois)

- CA / profit / dépenses mois
- Évolution vs mois précédent
- Top ventes & catégories
- Produits dormants & expirés
- Valeur stock fin de mois
- Prévisions heuristiques simples (P0 : moyenne mobile 3 mois — placeholder port IA)

## Exports manuels

- Audit PDF/Excel
- Catalogue Excel
- Mouvements de stock Excel
- Ventes période Excel
