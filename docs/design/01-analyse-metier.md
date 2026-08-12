# 01 — Analyse métier complète

**Produit** : Manolya Pharma  
**Marché primaire** : Congo (pharmacies indépendantes et chaînes)  
**Locale** : `fr` · Fuseau tenant : `Africa/Brazzaville` ou `Africa/Kinshasa`  
**Devises** : multi-devises (`XAF` / `CDF` / autres) — défaut par tenant  
**Paiements** : Cash, Carte, Mobile Money abstrait (Orange Money, Airtel Money, MTN MoMo)

---

## 1. Vision

Manolya Pharma est une plateforme SaaS de gestion intégrale de pharmacie : stocks par lots et expirations, POS rapide, achats fournisseurs, inventaires, finance, audit immuable, alertes et reporting décisionnel. Objectif : devenir la référence en Afrique centrale pour l’expérience, la traçabilité et la sécurité.

## 2. Problèmes métier résolus

| Problème | Impact | Réponse Manolya |
|----------|--------|-----------------|
| Pertes liées aux expirations | Marge et conformité | FEFO + alertes expiration |
| Ruptures / surstocks | CA perdu / capital immobilisé | Seuils min/critique + alertes |
| Manque de traçabilité | Fraude, litiges, contrôles | Mouvements immuables + audit |
| POS lent / papier | Files d’attente, erreurs | POS scan-first < 3 s |
| Visibilité propriétaire absente | Décisions à l’aveugle | Dashboard < 10 s + PDF auto |
| Multi-sites non géré | Chaînes bloquées | Multi-tenant / sites / entrepôts |

## 3. Acteurs

| Acteur | Description | Objectifs principaux |
|--------|-------------|----------------------|
| Super Admin | Opérateur plateforme SaaS | Tenants, billing technique, support |
| Propriétaire | Dirigeant pharmacie / chaîne | Pilotage CA, profit, stock, alertes |
| Pharmacien | Responsable scientifique / officine | Validation, ventes sensibles, conformité |
| Gestionnaire de Stock | Approvisionnement & stocks | Achats, réceptions, inventaires, lots |
| Caissier | Point de vente | Ventes rapides, encaissements, retours simples |
| Comptable | Finance | Dépenses, marges, rapprochements, exports |
| Auditeur | Contrôle interne / externe | Consultation audit, exports, pas de mutation métier |
| Système | Scheduler, queues, gateways | Jobs, rapports, alertes, webhooks paiement |

## 4. Périmètre fonctionnel (conception complète)

### 4.1 Identité & accès
Connexion sécurisée, 2FA, historique connexions, sessions, anti brute-force, RBAC, déconnexion forcée, reset password sécurisé.

### 4.2 Catalogue médicaments
Référence, noms commercial/générique, catégorie, fournisseur préféré, fabricant, codes-barres/QR, prix achat/vente/marge, seuils stock, image, import/export Excel-CSV, recherche Meilisearch.

### 4.3 Stock & lots
Lots (n°, fabrication, expiration, qty, coût), FEFO/FIFO, inventaire permanent, ajustements, corrections, historique. Détections : rupture, surstock, dormant, expiré, bientôt expiré.

### 4.4 Achats
Fournisseurs, demandes d’achat, bons de commande, réceptions, factures fournisseurs, paiements, validation hiérarchique, statuts.

### 4.5 Ventes / POS
Vente rapide, scan, facturation, tickets, cash/carte/MoMo, remises, retours, remboursements, maj stock temps réel.

### 4.6 Inventaire physique
Complet, tournant, partiel, comptage, écarts, validation, historisation.

### 4.7 Finance
Revenus, dépenses, marges, bénéfices, rapports j/h/m/a, graphiques.

### 4.8 Documents (GED)
Factures, contrats, BL, pièces, OCR async, versioning, recherche plein texte.

### 4.9 Audit
Append-only : auth, CRUD critiques, ventes, achats, retours, ajustements, prix, exports — timeline, filtres, PDF/Excel.

### 4.10 Alertes & reporting
Alertes multi-canaux (app, email, SMS-ready). PDF journalier 23:59 et mensuel fin de mois → email propriétaire.

### 4.11 Multi-organisation
Tenant → pharmacies/sites → entrepôts. V1 UI mono-site possible, modèle multi dès le départ.

### 4.12 IA (V2 préparée)
Ports pour prévision stock/ventes, anomalies, suggestions commandes, rentabilité — sans ML en V1.

## 5. Workflows métier critiques

### 5.1 Vente POS (happy path)

1. Caissier ouvre session caisse (optionnel V1.1).
2. Scan / recherche produit → résolution lot FEFO.
3. Quantité, remise éventuelle (selon permission).
4. Paiement (split possible : cash + MoMo).
5. Commit transaction : lignes vente + mouvements stock + écriture finance + audit + ticket.
6. Événements : `SaleCompleted`, `StockDecreased`, `PaymentRecorded`.

### 5.2 Réception achat

1. BC `approved` → réception partielle ou totale.
2. Saisie lots (n°, expiry, qty, coût).
3. Génération mouvements `IN_PURCHASE`.
4. Mise à jour facture fournisseur / dette.
5. Events : `GoodsReceived`, `BatchCreated`, `StockIncreased`.

### 5.3 Ajustement stock

1. Demande ajustement (motif obligatoire).
2. Validation selon seuil/rôle.
3. Mouvement `ADJUSTMENT` + audit old/new qty.
4. Alertes si écart anormal.

### 5.4 Inventaire physique

1. Création (complet / tournant / partiel).
2. Comptage → comparaison théorique vs réel.
3. Écarts listés → validation propriétaire/pharmacien.
4. Génération ajustements liés + clôture inventaire.

### 5.5 Expiration & alertes

1. Job quotidien scan lots (seuils configurables : J-90, J-60, J-30, J-7).
2. Création alertes + notifications.
3. Produits expirés bloqués à la vente (invariant).

## 6. Règles métier non négociables

1. Aucune vente d’un lot expiré ou qty insuffisante.
2. Déduction stock toujours au niveau **lot**.
3. Soft-delete uniquement ; motifs de suppression soft.
4. Audit append-only, non éditable via UI.
5. Prix de vente historisés à la ligne de vente (snapshot).
6. Coût de revient au lot pour marge réelle.
7. Isolation stricte par `tenant_id`.
8. Opérations critiques (ajustement, remise > seuil, remboursement, changement prix) : permission + audit.

## 7. Contexte Congo

- Langue UI : français.
- Paiements locaux prioritaires : espèces + Mobile Money (Orange, Airtel, MTN selon disponibilité).
- Coupures / réseau : POS doit tolérer latence ; queues pour non-bloquant (tickets email, sync MoMo).
- Fiscalité / numérotation factures : préfixes configurables par tenant (préparer champs, pas de verrouillage légal unique).
- Multi-devises : affichage et stockage selon devise tenant ; pas de conversion magique sans taux explicite (V1 : une devise active par tenant).

## 8. Hors scope V1 (implémentation)

- Modèles ML / prévisions IA (ports seulement).
- e-prescription nationale.
- Intégration grossistes EDI spécifiques.
- App mobile native (PWA possible plus tard).
- Comptabilité générale complète (plan comptable OHADA détaillé = V2+).

## 9. Critères de succès métier

- Propriétaire lit l’état de la pharmacie en &lt; 10 secondes sur le dashboard.
- Ticket POS finalisé en &lt; 3 secondes après paiement confirmé (hors latence gateway externe).
- 100 % des mouvements de stock traçables au lot.
- Zéro hard-delete des entités métier.
- Rapport PDF journalier reçu chaque nuit par le propriétaire.
