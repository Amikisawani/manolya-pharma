# Import des factures d’achat

Les photos de factures ont été saisies dans :

- `database/data/manolya_invoices_2026-09-22.json` — Compagnon EPVG, Avril Pharma, Pharmans, Unique Depot (~470 lignes)
- `database/data/manolya_invoices_2026-09-29.json` — Compagnon, Avril, Unique, La Confiance, Caisa, Africa Pharmacy, Promed Gros (295 lignes). Les noms Compagnon mal lus ont été corrigés, ainsi que les quantités et prix unitaires décalés (Falcidox, Femmex, Hommex, Ibunal, Effortil, Diclofen, Indalum 80). La ligne « Haemacel 3x10 » n’est pas sur la facture nette et a été retirée. Un import renomme les produits déjà en catalogue ; un lot déjà rempli garde sa quantité.
- `database/data/manolya_invoices_2026-10-06.json` — Avril SDKAGS26INS050191 (25 articles, Botamycin barré exclu) et Shahil Kins facture 26
- `database/data/manolya_invoices_2026-10-08.json` — Santevie 38227 et ticket Medico Plus 7WKYRX63 (lignes visibles + lots 1+1 offerts)

Sans `--file`, la commande charge **tous** les `database/data/manolya_invoices_*.json` (dates d’achat / péremption placeholder lues dans `_meta` de chaque fichier). Cet import **n’est pas** lancé au `db:seed`. Il faut l’exécuter une fois sur l’environnement cible (pilote / prod) après migrate — le boot Render le relance de façon idempotente.

## Commande

```bash
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa
```

Un fichier précis :

```bash
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa --file=database/data/manolya_invoices_2026-09-29.json
```

Options utiles :

```bash
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa --dry-run
php artisan manolya:import-invoice-purchases --tenant=manolya-kinshasa --warehouse=WH-MAIN --markup=1.2
```

## Règles métier

- Un même nom commercial (insensible à la casse) = **un seul produit**, plusieurs lots s’il apparaît sur plusieurs factures.
- SKU dérivé du nom (`AMBROXOL-15MG-…`).
- Lot : `ACH-{n° facture}-{SKU}` (suffixe `-2` si deux lignes du même produit sur la même facture).
- Réception via `StockMutator` / `IN_PURCHASE` — le produit devient vendable en caisse.
- Prix de vente = **prix unitaire facture × 1,2** (norme), arrondi à l’unité. Relancer l’import **recalcule** les prix déjà en catalogue.
- Lignes à coût 0 (gratuits / OCR) : produit + stock quand même ; le prix de vente est repris d’une ligne payée du même nom si elle existe.
- Dates de péremption absentes : placeholder **+1 an** (`2027-09-22`, `2027-09-29`, `2027-10-06`, `2027-10-08` selon le fichier). À corriger lot par lot dès que les dates réelles sont connues.
- Relancer la commande est **idempotent** si le lot a déjà du stock.
- Si un lot `ACH-*` existe avec **quantité 0** (import incomplet), un second passage **réinjecte la Qté facture** — c’est la colonne Quantité / Qté des bons (pas le n° de ligne).

## Caisse et stock enregistré

Tant que `SALES_ENFORCE_STOCK` est `false` (défaut), une vente n’est **pas** refusée si le lot est à 0 : on s’aligne sur le stock réel des étalages.

## Après import

1. Vérifier le catalogue et les stocks (dépôt `WH-MAIN`).
2. Corriger les prix de vente sensibles (injectables, princeps).
3. Saisir les dates de péremption réelles sur les lots `ACH-*`.
