# 05 — User Stories

Priorités : **P0** Phase 2 MVP · **P1** Phase 3 · **P2** Phase 4 · **P3** V2 IA  
Estimation relative : S / M / L

---

## P0 — Foundation & MVP

| ID | En tant que | Je veux | Afin de | Size |
|----|-------------|---------|---------|------|
| US-AUTH-01 | Utilisateur | me connecter avec email/mdp + 2FA | sécuriser l’accès | M |
| US-AUTH-02 | Propriétaire | voir l’historique des connexions | détecter les accès suspects | S |
| US-AUTH-03 | Propriétaire | forcer la déconnexion d’une session | couper un accès compromis | S |
| US-AUTH-04 | Système | bloquer après N échecs | limiter le brute-force | S |
| US-TEN-01 | Super Admin | créer un tenant pharmacie | onboarder un client | M |
| US-RBAC-01 | Propriétaire | assigner rôles/permissions | contrôler qui fait quoi | M |
| US-CAT-01 | Gestionnaire | créer/éditer un médicament | tenir le catalogue | M |
| US-CAT-02 | Caissier | rechercher par nom/barcode instantanément | encaisser vite | M |
| US-CAT-03 | Gestionnaire | importer Excel/CSV | initialiser le catalogue | L |
| US-STK-01 | Gestionnaire | gérer les lots (n°, expiry, qty) | tracer le stock | L |
| US-STK-02 | Système | allouer FEFO à la vente | réduire les péremptions | M |
| US-STK-03 | Gestionnaire | ajuster le stock avec motif | corriger les écarts | M |
| US-STK-04 | Propriétaire | voir ruptures / critiques / expirations | agir à temps | M |
| US-PUR-01 | Gestionnaire | gérer fournisseurs | centraliser les achats | S |
| US-PUR-02 | Gestionnaire | créer et faire approuver un BC | commander proprement | L |
| US-PUR-03 | Gestionnaire | réceptionner avec saisie de lots | entrer le stock correctement | L |
| US-POS-01 | Caissier | vendre via scan + paiement cash/carte/MoMo | servir le client | L |
| US-POS-02 | Caissier | appliquer une remise autorisée | fidéliser / corriger | S |
| US-POS-03 | Pharmacien | faire un retour/remboursement | gérer les réclamations | M |
| US-POS-04 | Caissier | réimprimer un ticket | fournir une preuve | S |
| US-DASH-01 | Propriétaire | voir CA/profit/stock critiques en &lt; 10 s | piloter | L |
| US-ALT-01 | Propriétaire | recevoir alertes rupture/expiration in-app + email | anticiper | M |
| US-AUD-01 | Auditeur | consulter la timeline d’audit filtrable | contrôler | M |

## P1 — Opérations avancées

| ID | En tant que | Je veux | Afin de | Size |
|----|-------------|---------|---------|------|
| US-CNT-01 | Gestionnaire | lancer inventaire complet/partiel/tournant | fiabiliser le stock | L |
| US-CNT-02 | Pharmacien | valider les écarts d’inventaire | autoriser les ajustements | M |
| US-FIN-01 | Comptable | enregistrer dépenses et voir marges | suivre la rentabilité | M |
| US-FIN-02 | Propriétaire | graphiques CA/profit/dépenses | décider | M |
| US-DOC-01 | Comptable | stocker factures/BL avec versions | centraliser les pièces | M |
| US-DOC-02 | Système | OCR async + index | retrouver un document | L |
| US-RPT-01 | Système | PDF journalier 23:59 email | informer le propriétaire | L |
| US-RPT-02 | Système | PDF mensuel fin de mois | bilan mensuel | L |
| US-CAT-04 | Gestionnaire | export Excel catalogue/stock | reporting externe | S |
| US-AUD-02 | Auditeur | exporter audit PDF/Excel | dossier de contrôle | M |

## P2 — SaaS durci

| ID | En tant que | Je veux | Afin de | Size |
|----|-------------|---------|---------|------|
| US-SITE-01 | Propriétaire | gérer multi-sites / entrepôts | chaînes | L |
| US-ALT-02 | Propriétaire | SMS sur alertes critiques | être joint hors app | M |
| US-OPS-01 | Super Admin | CI, Sentry, backups | fiabilité production | L |
| US-POS-05 | Caissier | POS ultra-optimisé offline-tolerant léger | tenir si réseau faible | L |

## P3 — V2 IA

| ID | En tant que | Je veux | Afin de | Size |
|----|-------------|---------|---------|------|
| US-AI-01 | Gestionnaire | suggestions de réassort | commander mieux | L |
| US-AI-02 | Propriétaire | prévision ventes/stock | anticiper | L |
| US-AI-03 | Auditeur | détection anomalies ventes/connexions | fraude | L |

## Critères d’acceptation transverses (tous P0+)

- Isolation `tenant_id` vérifiée sur chaque endpoint.
- Soft-delete sur entités métier.
- Audit sur actions listées module Audit.
- Permissions enforced via Policies Laravel.
- UI FR, montants formatés devise tenant.
