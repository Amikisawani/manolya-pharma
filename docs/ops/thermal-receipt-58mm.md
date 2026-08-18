# Ticket thermique 58 mm (GOOJPRT PT-210)

Le ticket caisse n’est plus une facture A4. L’A4 (ou un timbre 58 mm posé sur une page A4) imprimée sur le PT-210 sortait en **caractères microscopiques** : Windows « ajustait » toute la page A4 au rouleau 58 mm.

## Impression

Le bouton **Imprimer 58 mm** ouvre un document HTML isolé (`/sales/{id}/receipt`) qui **remplit 100 %** de la largeur du papier choisi. Ce n’est pas `window.print()` de la page caisse.

1. Encaisser une vente → impression automatique du ticket isolé.
2. Ou Ventes → Ticket → **Imprimer 58 mm**.
3. Dans Windows :
   - imprimante **PT-210** / **POS-58**
   - papier **58 mm rouleau** (pas un format fixe 58×40 mm)
   - échelle **100 %**
   - décocher **Ajuster à la page** / Fit to page
   - décocher en-têtes et pieds de page

Le PDF (téléchargement) est aussi au format **58 mm**, plus une facture A4.
