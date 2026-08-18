# Catalogue & stock initial

## Pourquoi un nouveau produit n’est pas vendable tout de suite

Dans Manolya, **la fiche produit** (catalogue) et **le stock** (lots) sont séparés :

1. *Nouveau produit* enregistre le médicament (nom, SKU, prix…).
2. La caisse ne vend que s’il existe un **lot** avec une quantité > 0 dans l’entrepôt (FEFO).
3. Sans lot, l’encaissement échoue : *stock insuffisant*.

C’est volontaire : une pharmacie suit les lots et les dates de péremption.

## Comment mettre en stock dès la création

Sur **Nouveau produit**, bloc **Stock initial** :

- quantité
- n° de lot
- péremption
- entrepôt

Le produit est alors vendable en caisse immédiatement.

Sans quantité, le message rappelle d’ajouter un lot via **Stock & lots** ou une **réception d’achat**.

## Fichier Excel 50 médicaments

Téléchargement : Catalogue → **Modèle Excel 50 médicaments**  
(`GET /catalog/products/template`)

Copie versionnée : `docs/samples/manolya-catalogue-50-medicaments.xlsx`

Colonnes = formulaire + stock :

`sku, commercial_name, generic_name, barcode, manufacturer, purchase_price, sale_price, currency_code, min_stock, critical_stock, allocation_strategy, category, description, supplier, initial_qty, lot_number, expires_at, warehouse`

`warehouse` = code (ex. `WH-MAIN`).  
`initial_qty` vide = catalogue seul, pas de lot.

## Import en production

Si l’import échoue, un bandeau rouge s’affiche (plus une page d’erreur vide). Causes fréquentes :

- fichier `.xls` (Excel 97) — exporter en **.xlsx**
- dates de péremption illisibles — préférer `AAAA-MM-JJ` (ex. `2027-12-31`) ou `JJ/MM/AAAA`
- aucun dépôt créé pour la pharmacie
- fichier corrompu ou trop lourd (> 10 Mo)

Une ligne invalide n’annule plus tout le fichier : les lignes correctes sont importées, les autres sont listées dans le message.

En production (Render / Coolify), l’import tourne **hors requête HTTP** (`php artisan catalog:import`). Sinon le serveur PHP bloqué dépasse le timeout (~30 s) : health-check KO → **502 Bad Gateway** pour tout le site. Actualisez le catalogue quelques secondes après l’envoi du fichier.
