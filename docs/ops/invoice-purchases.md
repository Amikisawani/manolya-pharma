# Import des factures d’achat (22/09/2026)

Les photos de factures (Compagnon EPVG, Avril Pharma, Pharmans, Unique Depot) ont été saisies dans :

`database/data/manolya_invoices_2026-09-22.json`

(~470 lignes). Cet import **n’est pas** lancé au `db:seed`. Il faut l’exécuter une fois sur l’environnement cible (pilote / prod) après migrate.

## Commande

```bash
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa
```

Options utiles :

```bash
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa --dry-run
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa --warehouse=WH-MAIN --markup=1.4
```

## Règles métier

- Un même nom commercial (insensible à la casse) = **un seul produit**, plusieurs lots s’il apparaît sur plusieurs factures.
- SKU dérivé du nom (`AMBROXOL-15MG-…`).
- Lot : `ACH-{n° facture}-{SKU}` (suffixe `-2` si deux lignes du même produit sur la même facture).
- Réception via `StockMutator` / `IN_PURCHASE` — le produit devient vendable en caisse.
- Prix de vente **absent des factures** : `round(prix_achat × 1.4)` (à ajuster ensuite dans le catalogue).
- Lignes à coût 0 (gratuits / OCR) : produit + stock quand même ; le prix de vente est repris d’une ligne payée du même nom si elle existe.
- Dates de péremption absentes : placeholder **2027-09-22**. À corriger lot par lot dès que les dates réelles sont connues.
- Relancer la commande est **idempotent** si le lot a déjà du stock.
- Si un lot `ACH-*` existe avec **quantité 0** (import incomplet), un second passage **réinjecte la Qté facture** — c’est la colonne Quantité / Qté des bons (pas le n° de ligne).

## Caisse et stock enregistré

Tant que `SALES_ENFORCE_STOCK` est `false` (défaut), une vente n’est **pas** refusée si le lot est à 0 : on s’aligne sur le stock réel des étalages.

## Après import

1. Vérifier le catalogue et les stocks (dépôt `WH-MAIN`).
2. Corriger les prix de vente sensibles (injectables, princeps).
3. Saisir les dates de péremption réelles sur les lots `ACH-*`.
