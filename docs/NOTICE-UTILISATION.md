# Manolya Pharma — Notice d’aide à l’utilisation

> Guide pratique pour le personnel d’officine  
> Devise principale : **Franc congolais (Fc)** · équivalents USD / EUR affichés  
> Dernière mise à jour : 2026-08-15

---

## 1. Démarrage

### Connexion
1. Ouvrir l’adresse de Manolya fournie par votre administrateur.
2. Saisir e-mail + mot de passe.
3. Si la double authentification (2FA) est activée, entrer le code de l’application d’authentification.

### Navigation
- À gauche : menu **fixe** (marque + pharmacie en haut, déconnexion en bas).
- Au centre : contenu de la page (scroll indépendant).
- Sur mobile : bouton **Menu** en haut à gauche.
- Raccourci fréquent : **Ouvrir la caisse** (en-tête).

### Rôles (résumé)
| Rôle | Ce qu’il fait surtout |
|------|------------------------|
| Propriétaire | Tout le tenant, clôtures, e-mails de rapport |
| Pharmacien | Catalogue, stock, achats, caisse, validations |
| Gestionnaire de stock | Lots, réceptions, inventaires |
| Caissier | Caisse / ventes / sessions |
| Comptable | Finance |
| Auditeur | Consultation audit (lecture) |

Si un bouton est absent, c’est en général une question de **permission** — demandez au propriétaire.

---

## 2. Parcours recommandé de la journée

1. **Ouvrir une session de caisse** (fond de caisse).
2. Vendre depuis **Caisse**.
3. Imprimer / télécharger la **facture PDF** si besoin.
4. Traiter retours éventuels depuis la fiche vente.
5. **Clôturer la session** (comptage espèces) → rapport PDF envoyé aux propriétaires (si messagerie configurée).

---

## 3. Caisse (POS)

**Menu :** Caisse

### Avant de vendre
- Une **session de caisse ouverte** est obligatoire.
- Aller dans **Sessions** → ouvrir avec le fond de caisse (Fc).

### Vendre
1. Rechercher un produit (nom, SKU, code-barres) — recherche dynamique.
2. Ajouter au panier, ajuster la quantité.
3. Choisir le(s) mode(s) de paiement : espèces, carte, Mobile Money.
4. Valider → redirection vers la fiche vente / facture.

### Stock à la vente
- Le système prélève automatiquement sur les **lots** (stratégie FEFO / FIFO du produit).
- Si message *Insufficient stock* : le produit n’a pas de quantité disponible dans l’entrepôt de la caisse. Faire une **réception** ou un **ajustement** avant de revendre.

---

## 4. Sessions de caisse

**Menu :** Sessions

| Action | Quand |
|--------|--------|
| Ouvrir | Début de service — saisir le fond de caisse |
| Consulter | Voir ventes rattachées, totaux |
| Clôturer | Fin de service — saisir les espèces **comptées** |

À la clôture, Manolya calcule l’**écart** (attendu vs compté).  
Si l’e-mail est bien configuré, les comptes **owner actifs** reçoivent un **PDF de clôture** (détail ventes, paiements, alertes stock).

---

## 5. Ventes & facture PDF

**Menu :** Ventes

- Liste filtrable (n° ticket, dates).
- Ouvrir un ticket → **aperçu PDF** (facture propre, sans en-têtes navigateur).
- **Ouvrir / imprimer** : visionneuse PDF native.
- **Télécharger PDF** : fichier `facture-SL-….pdf`.
- **Retour / remboursement** (si permission) : quantité, motif, mode de remboursement, option « Remettre en stock ».

Astuce : pour rattacher un remboursement cash à la clôture, gardez une session ouverte.

---

## 6. Catalogue médicaments

**Menu :** Catalogue

### Créer / modifier un produit
- Nom commercial, SKU, DCI / générique.
- Catégorie (recherche dans la liste pharmacie).
- Prix d’achat / vente (Fc), stock min / critique.
- Stratégie d’allocation : FEFO (recommandé), FIFO, LIFO.
- Fournisseur préféré (optionnel).

### Import Excel / CSV
1. Bouton **Import Excel/CSV**.
2. Formats : `.xlsx` ou `.csv` (séparateur `;`).
3. En-têtes FR ou EN acceptés, par exemple :

| Colonne | Exemples d’en-tête |
|---------|-------------------|
| Nom | Nom commercial |
| SKU | SKU |
| DCI | DCI / générique |
| Catégorie | Catégorie |
| Prix | Prix d’achat, Prix de vente |
| Stocks seuils | Stock min, Stock critique |
| Devise | Devise (CDF, USD, EUR) |
| Stock initial (optionnel) | quantite, stock_initial, lot, expiration |

**Important :**
- L’import crée / met à jour les **fiches produit**.
- Il crée aussi un **lot de stock initial** s’il n’y a pas encore de quantité (sinon il ne double pas le stock).
- Sans colonne quantité : quantité d’ouverture ≈ `max(stock min × 2, 50)`.
- Les prix en USD/EUR dans un fichier modèle doivent être convertis en **Fc** avant import (ou déjà en CDF dans la colonne Devise).

### Export
- **Export Excel** ou **Export CSV** pour sauvegarde / reprise.

---

## 7. Stock & lots

**Menu :** Stock · Mouvements

### Lots
- Chaque entrée de stock = un **lot** (n°, péremption, quantité, coût).
- Filtres : statut (actif, expiré, quarantaine, épuisé), recherche lot / produit.
- **Ajustement** : delta (+/−) + motif obligatoire (traçabilité).

### Mouvements
- Historique des entrées / sorties (`IN_PURCHASE`, `OUT_SALE`, ajustements, etc.).
- Export Excel possible.

Quantités affichées en français : **12,000** = douze (3 décimales), pas douze mille.

---

## 8. Achats & réception

**Menu :** Achats

1. Créer un bon de commande (lignes produit + quantités + coûts).
2. Faire approuver (selon rôle).
3. **Réceptionner** : entrepôt, quantité reçue, **n° de lot**, **date d’expiration**.
4. La réception crée le lot et augmente le stock (mouvement d’achat).

Sans réception, un produit catalogue **n’est pas vendable**.

---

## 9. Inventaires

**Menu :** Inventaires

1. Ouvrir un comptage sur un entrepôt.
2. Saisir les quantités comptées ligne par ligne.
3. Soumettre, puis **valider les écarts** (rôle autorisé) → ajustements de stock.

---

## 10. Finance, documents, alertes, audit

| Menu | Usage |
|------|--------|
| **Finance** | Vue dépenses / indicateurs financiers |
| **Documents** | Dépôt et consultation de documents |
| **Alertes** | Ruptures, stock critique, expirations proches |
| **Audit** | Journal des actions (lecture / export selon droit) |
| **Sites** | Sites / configuration (owner) |
| **Compte** | Profil, mot de passe |

---

## 11. Tableau de bord

**Menu :** Tableau de bord

Vue rapide : CA, ventes, stock critique, expirations, graphiques.  
Objectif : comprendre l’état de l’officine en quelques secondes.

---

## 12. Bonnes pratiques

1. Toujours ouvrir / clôturer la caisse le jour même.
2. Ne jamais vendre sans lot (corriger le stock plutôt que contourner).
3. Renseigner péremption à chaque réception.
4. Garder des SKU uniques et stables.
5. Vérifier les alertes en début de journée.
6. Imprimer la facture PDF pour le client ; conserver l’historique dans **Ventes**.
7. Ne pas partager le compte owner ; créer un compte caissier dédié.

---

## 13. Problèmes fréquents

| Symptôme | Cause probable | Que faire |
|----------|----------------|-----------|
| Impossible de vendre | Pas de session ouverte | Ouvrir une session |
| *Insufficient stock* | Pas de lot / qté 0 | Réception ou ajustement |
| Import 0 créé | En-têtes mal lus / fichier .xls | Utiliser .xlsx ; vérifier la 1ʳᵉ ligne d’en-têtes |
| Prix à 0 après import | Colonnes prix vides | Remplir Prix d’achat / vente en Fc |
| PDF clôture non reçu | SMTP / file d’attente | Voir l’admin (section messagerie) |
| Menu incomplet | Rôle limité | Demander les droits au owner |

---

## 14. Support interne

- Propriétaire / pharmacien responsable de l’officine.
- Documentation technique déploiement : `docs/ops/deploy.md`.
- Suivi produit : `docs/ETAT-AVANCEMENT.md`.

---

*Manolya Pharma — gestion d’officine claire et traçable.*
