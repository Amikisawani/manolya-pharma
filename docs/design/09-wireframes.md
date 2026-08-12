# 09 — Wireframes & UX

## Principes

- Inspirations : Linear, Stripe, Notion, Vercel, Shopify Admin — **jamais** AdminLTE.
- Typographie expressive (ex. `Satoshi` / `Instrument Sans` + `JetBrains Mono` données).
- Fond atmosphérique subtil (grain/gradient doux), pas flat gris admin.
- Brand **Manolya Pharma** visible hero-level sur écran login.
- Dashboard propriétaire = une composition claire, pas un mur de widgets.
- POS = plein écran, clavier/scan first, zéro carte décorative.

## Shell applicatif

```
┌──────────────┬────────────────────────────────────────────┐
│ Manolya      │ Topbar: search ⌘K · alerts · user          │
│ Pharma       ├────────────────────────────────────────────┤
│              │                                            │
│ Dashboard    │              Page content                  │
│ POS          │                                            │
│ Catalogue    │                                            │
│ Stock        │                                            │
│ Achats       │                                            │
│ Inventaires  │                                            │
│ Finance      │                                            │
│ Documents    │                                            │
│ Audit        │                                            │
│ Paramètres   │                                            │
└──────────────┴────────────────────────────────────────────┘
```

Navigation gauche compacte (icônes + label), état actif souligné, collapsible mobile.

## Login

- Plein viewport : brand grand format + champ email/mdp + CTA unique.
- Pas de cards stats ; motion léger (fade brand, focus input).
- Étape 2FA plein écran dedicated.

## Dashboard exécutif

Premier viewport :
1. Brand / nom pharmacie
2. Une ligne : CA jour · CA mois · Profit jour · Profit mois
3. Un graphique évolution (ApexCharts)
4. CTA : « Ouvrir POS » / « Voir alertes »

Sous le fold : top produits, critiques/expirations/ruptures, valeur stock, top catégories.  
**Pas** de tableau admin dense en hero.

## POS

```
┌─────────────────────────────┬──────────────────┐
│ Scan / Recherche            │ Panier           │
│ Résultats produits          │ Lignes           │
│                             │ Totaux           │
│                             │ Paiements        │
│                             │ [Encaisser]      │
└─────────────────────────────┴──────────────────┘
```

Raccourcis : F2 search, F9 payer, Esc clear. Feedback scan haptique/son court optionnel.

## Stock

- Liste produits + badge qty / expiry risk.
- Fiche produit : onglets Infos | Lots | Mouvements | Historique.
- Filtres avancés latéraux (expiry &lt; 30j, rupture, dormant).

## Achats

Kanban léger ou table status pills : Draft → Approval → Approved → Receiving → Closed.  
Réception : formulaire lots inline.

## Audit

Timeline verticale (Notion/Linear activity) : filtre chips, détail drawer old/new JSON diff.

## Design tokens (CSS variables)

```css
--color-bg: …
--color-fg: …
--color-accent: /* vert pharmacie profond, pas purple AI */
--color-danger: …
--color-warning: …
--radius-sm/md: …
--font-sans / --font-display / --font-mono
```

Éviter : purple-on-white, cream+terracotta cliché, broadsheet newspaper.
