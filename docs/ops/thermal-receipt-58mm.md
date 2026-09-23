# Ticket thermique 58 mm (GOOJPRT PT-210)

Le ticket caisse n’est plus une facture A4. L’A4 (ou un timbre 58 mm posé sur une page A4) imprimée sur le PT-210 sortait en **caractères microscopiques** : Windows « ajustait » toute la page A4 au rouleau 58 mm.

## Impression

Le bouton **Imprimer 58 mm** ouvre le document HTML isolé (`/sales/{id}/receipt?autoprint=1`) dans une **fenêtre séparée**. Cette page appelle `window.print()` elle-même.

On n’imprime plus via un iframe caché : `X-Frame-Options: DENY` bloque le ticket dans un cadre, donc `contentWindow.print()` ne faisait rien (le bouton semblait mort).

1. Encaisser une vente → ouverture du ticket isolé (ou navigation vers le ticket si le navigateur bloque la popup) → dialogue d’impression.
2. Ou Ventes → Ticket → **Imprimer 58 mm**.
3. Si le dialogue ne s’ouvre pas tout seul, le ticket affiche une barre **Imprimer** / **Voir la vente** / **Retour caisse**.
4. Dans Windows :
   - imprimante **PT-210** / **POS-58** / **GOOJPRT**
   - papier **58 mm rouleau** (pas un format fixe 58×40 mm)
   - échelle **100 %**
   - décocher **Ajuster à la page** / Fit to page
   - décocher en-têtes et pieds de page

Le PDF (téléchargement) est aussi au format **58 mm**, plus une facture A4.
