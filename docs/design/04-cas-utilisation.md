# 04 — Cas d'utilisation

Format : **UC-ID** — Acteur principal — Préconditions — Flux — Postconditions — Exceptions.

---

## Identity

### UC-AUTH-01 Connexion
- **Acteur** : Tout utilisateur
- **Pré** : Compte actif, non verrouillé
- **Flux** : email/password → si 2FA activé challenge TOTP → session + `UserLoggedIn`
- **Post** : session valide, audit connexion
- **Exc** : credentials invalides (compteur), compte locked, 2FA invalide

### UC-AUTH-02 Déconnexion forcée
- **Acteur** : Propriétaire / Super Admin
- **Flux** : sélection session → revoke → `SessionRevoked`
- **Post** : token invalidé immédiatement

### UC-AUTH-03 Reset password
- **Flux** : demande → email token one-time → nouveau mdp → revoke autres sessions

---

## Catalog

### UC-CAT-01 Créer / modifier médicament
- **Acteur** : Gestionnaire Stock / Pharmacien / Propriétaire
- **Post** : product persisté, indexé Scout, audit si prix changé

### UC-CAT-02 Recherche instantanée
- **Acteur** : Caissier+
- **Flux** : query → Meilisearch (sku, nom, barcode) → résultats &lt; 200 ms cible

### UC-CAT-03 Import Excel/CSV
- **Acteur** : Gestionnaire Stock
- **Flux** : upload → validation lignes → job import → rapport succès/erreurs

---

## Inventory

### UC-INV-01 Consulter stock par lots
- Filtres : produit, entrepôt, expiry window, status

### UC-INV-02 Ajuster stock
- Motif obligatoire ; permission ; mouvement immuable ; audit old/new

### UC-INV-03 Transfert inter-entrepôts
- OUT + IN atomiques même lot méta

---

## Purchasing

### UC-PUR-01 Créer bon de commande
- Lignes produits/qty → `draft` → submit → approval selon montant

### UC-PUR-02 Approuver BC
- **Acteur** : Propriétaire / Pharmacien (seuil)
- **Post** : `approved`, notif acheteur

### UC-PUR-03 Réceptionner
- Saisie lots/expiry/qty → stock↑ → BC status partial/received

### UC-PUR-04 Payer fournisseur
- Lien facture → payment → solde

---

## Sales

### UC-POS-01 Vente rapide
- Scan/search → qty → paiement(s) → complete → ticket
- **Exc** : stock insuffisant, lot expiré, paiement failed, remise non autorisée

### UC-POS-02 Retour / remboursement
- Sélection vente → lignes → restock oui/non → payment reverse → audit

### UC-POS-03 Annulation vente (void)
- Fenêtre courte + permission élevée ; reverse stock si completed

---

## Stock count

### UC-CNT-01 Lancer inventaire
- Type full/rotating/partial → scope produits → open

### UC-CNT-02 Comptage & validation
- Saisie counted → variances → validate → auto adjustments

---

## Finance

### UC-FIN-01 Enregistrer dépense
- Catégorie, montant, pièce jointe optionnelle (GED)

### UC-FIN-02 Consulter marges
- Période → CA, coût, profit, graphiques

---

## Documents

### UC-DOC-01 Upload document
- Métadonnées + fichier → storage → version 1 → job OCR optionnel

### UC-DOC-02 Recherche documentaire
- Full-text + filtres type/date

---

## Audit & Alerts & Reports

### UC-AUD-01 Explorer timeline audit
- Filtres user, action, entité, date, IP → détail old/new → export

### UC-ALT-01 Traiter alerte
- Inbox → acknowledge / resolve

### UC-RPT-01 Rapport journalier auto
- **Système** 23:59 timezone tenant → PDF → email propriétaires

### UC-RPT-02 Rapport mensuel auto
- Dernier jour du mois → PDF enrichi → email

---

## Dashboard

### UC-DASH-01 Vue exécutive
- **Acteur** : Propriétaire
- CA j/m, profit j/m, dépenses, top ventes, critiques, expirés, bientôt, ruptures, valeur stock, top catégories, évolution mensuelle
