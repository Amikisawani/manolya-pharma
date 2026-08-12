# MANOLYA PHARMA — Dossier de Conception V1

> **Statut** : Conception **validée (GO)** — marché Congo, build par phases.  
> **Produit** : Plateforme SaaS de gestion intégrale de pharmacie (Congo → multi-pays).  
> **Version conception** : 1.0.0 — 2026-08-12

## Livrables

| # | Document | Fichier |
|---|----------|---------|
| 1 | Analyse métier complète | [01-analyse-metier.md](./01-analyse-metier.md) |
| 2 | Domain Model | [02-domain-model.md](./02-domain-model.md) |
| 3 | Event Storming | [03-event-storming.md](./03-event-storming.md) |
| 4 | Cas d'utilisation | [04-cas-utilisation.md](./04-cas-utilisation.md) |
| 5 | User Stories | [05-user-stories.md](./05-user-stories.md) |
| 6 | Architecture logicielle | [06-architecture.md](./06-architecture.md) |
| 7 | Schéma base de données | [07-schema-bdd.md](./07-schema-bdd.md) |
| 8 | Diagrammes UML | [08-uml.md](./08-uml.md) |
| 9 | Wireframes & UX | [09-wireframes.md](./09-wireframes.md) |
| 10 | Arborescence projet | [10-arborescence.md](./10-arborescence.md) |
| 11 | Permissions RBAC | [11-permissions.md](./11-permissions.md) |
| 12 | Rapports & Alertes | [12-rapports-alertes.md](./12-rapports-alertes.md) |
| 13 | Validation conception | [13-validation.md](./13-validation.md) |
| 14 | Roadmap V1 / V2 | [14-roadmap.md](./14-roadmap.md) |

## Suivi d’avancement

Voir le document vivant : [../ETAT-AVANCEMENT.md](../ETAT-AVANCEMENT.md) (concept, terminé, restant).

## Principes non négociables

1. **Traçabilité totale** — tout mouvement de stock, financier et critique est journalisé.
2. **Soft-delete uniquement** — aucune suppression définitive des données métier.
3. **Multi-tenant ready** — isolation par `tenant_id` dès V1 (même mono-pharmacie).
4. **Lot + expiration** — FEFO par défaut, FIFO optionnel par catégorie.
5. **Audit immuable** — append-only, non modifiable par l'UI.
6. **Sécurité by design** — MFA, RBAC granulaire, rate-limit, chiffrement au repos des secrets.
7. **POS < 3 secondes** — parcours vente caisse optimisé scan → paiement → ticket.

## Décision de go / no-go

Le développement ne démarre qu'après validation explicite du document [13-validation.md](./13-validation.md).
