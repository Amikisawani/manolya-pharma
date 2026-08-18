# Ticket thermique 80 mm

Le ticket de caisse POS est généré **uniquement à partir de la vente enregistrée**. Une réimpression ne crée pas de nouvelle vente et ne modifie aucun montant.

## Où se trouve le template

- Composant Vue : `resources/js/Components/Receipt80mm.vue`
- Données : `app/Domain/Sales/Receipts/ThermalReceipt.php`
- Construction serveur : `app/Domain/Sales/Receipts/ThermalReceiptBuilder.php`

L’aperçu (page vente) et l’impression utilisent le même composant.

## CSS d’impression

`resources/css/receipt-80mm.css` (importé depuis `resources/js/app.ts`)

- largeur papier **80 mm**, zone utile ~72 mm avec marges internes
- `@media print` masque sidebar, menus, boutons et formulaires (`mp-no-print`)
- seul `#receipt-80mm` est envoyé à l’imprimante

Sur Windows : dans la boîte de dialogue, choisir l’imprimante thermique 80 mm, format **80 mm**, **sans** « Ajuster à la page ».

## Déroulement

1. Encaisser au POS (`/pos`)
2. La vente est enregistrée (`CompleteSaleService`)
3. Redirection vers `/sales/{id}` avec aperçu 80 mm
4. **🖨 Imprimer le ticket** ouvre le dialogue d’impression du navigateur
5. Historique : **Voir** / **Réimprimer** / **Détails**
   - Réimprimer → `/sales/{id}/reprint` (même ticket, bandeau DUPLICATA)

## Impression automatique

Paramètre par site, **désactivé par défaut** :

**Sites & entrepôts** → *Impression automatique des tickets* : Oui / Non

Si Oui, le dialogue d’impression s’ouvre après validation de la vente.

## Informations de la pharmacie

Toujours dans **Sites & entrepôts** :

- nom, adresse, téléphone, e-mail
- RCCM, ID Nat, NIF
- logo (optionnel, imprimé en niveaux de gris)

Ces champs vides sont omis du ticket.

## Pied de ticket

Champ **Message de pied de ticket** (ex. « Votre santé, notre priorité. »).

Les **conditions de retour** s’affichent seulement si le champ est renseigné.

QR code : Oui / Non (généré avec `bacon/bacon-qr-code`, payload `MANOLYA|{n° vente}|{date}`).

## Montants

Formatage serveur : `app/Domain/Shared/Formatting/MoneyFormatter.php`  
Exemple : `1 500 Fc`, `25 000 Fc`.

Le ticket n’expose ni coût d’achat ni marge.
