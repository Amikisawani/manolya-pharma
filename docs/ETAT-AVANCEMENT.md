# MANOLYA PHARMA — Concept & état d’avancement

> Document de pilotage produit/technique  
> Dernière mise à jour : 2026-08-13  
> Marché cible : Congo (RDC) — devise principale **Fc (CDF)**

---

## 1. Concept général

**Manolya Pharma** est une plateforme SaaS de **gestion intégrale de pharmacie** destinée aux officines indépendantes et, à terme, aux chaînes multi-sites.

Elle couvre le cycle opérationnel d’une pharmacie moderne :

- vente / caisse (POS)
- catalogue médicaments
- stock par **lots** et **dates d’expiration** (FEFO)
- achats fournisseurs
- inventaires
- finance & reporting
- documents
- alertes
- audit / traçabilité
- utilisateurs & permissions

### Promesse produit

- Comprendre l’état de la pharmacie en **moins de 10 secondes** (dashboard)
- Encaisser vite (POS scan / recherche)
- Ne jamais perdre la trace d’un mouvement de stock
- Sécurité et rôles dès le départ
- Prêt multi-pharmacies / multi-sites (architecture tenant), même si l’exploitation démarre mono-site

### Principes non négociables

1. Traçabilité totale des mouvements
2. Soft-delete (pas de suppression définitive métier)
3. Isolation multi-tenant (`tenant_id`)
4. Lots + expirations
5. Audit append-only
6. Affichage monétaire en **Fc**, avec équivalents **USD** et **EUR**

### Stack

| Couche | Techno |
|--------|--------|
| Backend | Laravel 13, PHP 8.4+, PostgreSQL, Sanctum, Spatie Permission |
| Frontend | Vue 3, TypeScript, Inertia, Tailwind |
| Async / search | Queues, Scheduler, Scout (Meilisearch prêt) |
| Ops | Docker Compose, GitHub Actions (base) |

Conception détaillée : [`docs/design/`](./design/)

---

## 2. État actuel (synthèse)

| Statut | Signification |
|--------|----------------|
| **MVP opérationnel** | Oui — parcours cœur utilisable en démo / pilote |
| **Produit fini marché** | Non — modules avancés et durcissement prod restants |
| **Compte démo** | `owner@manolya.test` / `password` |
| **Site démo** | Site Bandal (Kinshasa) |

---

## 3. Étapes terminées

### Phase 0 — Conception
- [x] Analyse métier
- [x] Domain model & event storming
- [x] Cas d’utilisation & user stories
- [x] Architecture DDD / Clean
- [x] Schéma PostgreSQL
- [x] UML, wireframes, arborescence
- [x] Matrice RBAC
- [x] Rapports / alertes (conception)
- [x] Validation GO + roadmap

### Phase 1 — Fondation
- [x] Scaffold Laravel + Inertia Vue TS
- [x] Auth (Breeze) + base 2FA
- [x] Multi-tenant (`tenant_id`, middleware)
- [x] Rôles / permissions Spatie
- [x] Audit logger
- [x] Docker Compose (défini)
- [x] Seed pharmacie démo Congo

### Phase 2 — MVP métier
- [x] Dashboard exécutif (KPIs + graphique)
- [x] POS / caisse (recherche **dynamique**, panier, encaissement, **session ouverte obligatoire**)
- [x] Sessions de caisse (ouverture / clôture / écart)
- [x] Retours / remboursements (ticket + restock)
- [x] Ticket de vente + historique ventes
- [x] Catalogue médicaments (CRUD)
- [x] Import / export CSV catalogue
- [x] Import / export Excel catalogue (OpenSpout .xlsx) + export ventes / mouvements

- [x] Stock & lots (FEFO à la vente)
- [x] Ajustements de stock
- [x] Historique des mouvements
- [x] Fournisseurs
- [x] Bons de commande (création, soumission, approbation, réception lots)
- [x] Alertes (UI + seed démo)
- [x] Devise **Fc** + équivalents **$ / €**
- [x] Design épuré (papier / encre / vert forêt)

### Phase 3 — Partiellement livré
- [x] Inventaires (ouverture, comptage, validation)
- [x] Finance (synthèse + dépenses)
- [x] Rapports PDF journalier / mensuel + e-mail propriétaire (PDF joint)
- [x] Documents (upload / liste / détail — OCR async + recherche plein texte)
- [x] Audit timeline
- [x] Jobs planifiés (expiries, seuils, PDF journalier/mensuel TZ Kinshasa)
- [ ] OCR cloud/Tesseract réel (port HTTP prêt via `OCR_DRIVER=http`)
- [x] Exports Excel natifs (catalogue, ventes, mouvements — CSV conservé)

### Phase 4 — Amorcée seulement
- [x] Multi-sites (création site / entrepôt UI)
- [x] Ports IA / SMS (stubs)
- [x] CI GitHub Actions (Postgres service + Feature tests)
- [x] Docs déploiement + script backup Postgres
- [x] Sentry branché (Laravel + Vue ; DSN prod à injecter — `docs/ops/sentry.md`)
- [x] SMS réel (adapters Orange/Airtel ; credentials à brancher)
- [x] Gateways Mobile Money (Orange/Airtel/MTN ; stub sans credentials)
- [ ] Durcissement perf / backups actifs en prod + monitoring

---

## 4. Étapes restantes (priorisées)

### P0 — Stabilisation pilote
1. [x] Retours / remboursements caisse (ticket + restock + rattachement session)
2. [x] Session caisse (ouverture / clôture / écart)
3. [x] Tests Feature élargis (achats, inventaire, permissions)
4. [x] Déploiement documenté (Coolify/Forge) — `docs/ops/deploy.md` + repo GitHub
5. [x] Sauvegardes PostgreSQL — `docs/ops/backups.md` + `scripts/backup-postgres.sh`

> Repo : https://github.com/Amikisawani/manolya-pharma — brancher Coolify/Forge sur `main`, puis cron backup.

### P1 — Opérations avancées
1. [x] Rapport PDF journalier / mensuel + email propriétaire (PDF joint)
2. [x] OCR GED (async) + recherche plein texte (FTS Postgres + extraction locale)
3. [x] Import / export Excel (OpenSpout) + CSV
4. [x] Alertes SMS Orange/Airtel (adapters HTTP + canal notification)
5. [x] Paiements Mobile Money (Orange / Airtel / MTN — HTTP ou stub)

> **P1 terminé.** Credentials SMS/MoMo + hébergeur : `docs/ops/sms-momo.md` + `docs/ops/deploy.md`.

### P2 — SaaS & scale
1. Onboarding multi-tenants (Super Admin)
2. Multi-sites / multi-entrepôts avancé (transferts, reporting consolidé)
3. [x] Sentry (Laravel + Vue) — reste : Horizon + Reverb temps réel
4. Meilisearch en prod (recherche catalogue/POS)
5. Paramétrage fiscal / numérotation factures Congo

### P3 — V2 IA décisionnelle
1. Prévision stock / ventes
2. Suggestions de réassort
3. Détection d’anomalies (ventes / connexions)
4. Analyse de rentabilité assistée

---

## 5. Périmètre volontairement hors V1

- e-prescription nationale
- Application mobile native
- Comptabilité OHADA complète
- EDI grossistes spécifiques
- Modèles ML en production

---

## 6. Comment juger “fini”

Le logiciel sera considéré **prêt production officines** quand :

- [ ] Un parcours complet achat → stock → vente → ticket → finance est stable en prod
- [x] Retours caisse + clôture de caisse (implémentés ; à valider en pilote)
- [ ] Backups + monitoring + Sentry actifs (code Sentry prêt ; DSN + backups cron prod)
- [x] PDF journalier / mensuel générés + e-mail propriétaire (à valider SMTP prod)
- [ ] Au moins un gateway Mobile Money réel testé
- [x] Jeu de tests Feature couvrant les flux critiques (local ; CI Postgres branchée)

---

## 7. Prochaine action proposée

**P0 + P1 terminés. Sentry code branché.** Injecter les DSN en prod (`docs/ops/sentry.md`), puis Horizon/Reverb ou onboarding Super Admin (P2).
