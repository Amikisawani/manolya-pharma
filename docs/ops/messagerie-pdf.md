# Messagerie PDF (clôture de caisse) — checklist fournisseur & appli

> Concerne aujourd’hui : **PDF de clôture de session** e-mailé aux **owners** (`SendCashSessionClosedReportJob` + `CashSessionClosedMail`).  
> Les factures de vente PDF s’ouvrent dans l’app ; l’envoi e-mail facture client n’est pas encore branché.

### Cas pilote actuel
- **Expéditeur (FROM)** : `amikisawani15@icloud.com`
- **Destinataire (TO)** : `amikisawani71@gmail.com` (= e-mail du compte **owner** dans Manolya)

---

## Parcours complet (Brevo → Manolya → Gmail)

```
[Clôture caisse]
      → Job SendCashSessionClosedReportJob (queue)
      → PDF généré + joint
      → Notification aux users rôle owner
      → SMTP Brevo (MAIL_*)
      → Boîte amikisawani71@gmail.com
```

---

## A. Côté Brevo (fournisseur SMTP)

1. Se connecter à [Brevo](https://app.brevo.com).
2. **Senders & IPs** (ou *Senders*) → ajouter / vérifier l’expéditeur  
   **`amikisawani15@icloud.com`**  
   - Brevo envoie un mail de confirmation sur iCloud → cliquer le lien.
3. **SMTP & API** → créer une **clé SMTP** (pas le mot de passe du compte Brevo).
4. Noter :
   - Host : `smtp-relay.brevo.com`
   - Port : `587`
   - Login : l’identifiant SMTP affiché (souvent l’e-mail du compte Brevo)
   - Password : la clé SMTP
5. Test Brevo (optionnel) : envoyer un mail test **From** iCloud **To** `amikisawani71@gmail.com`.  
   Si ça n’arrive pas ici, Manolya ne pourra pas non plus.

> Limite iCloud : pas de SPF/DKIM sur un domaine que vous ne contrôlez pas. Ça marche en pilote, mais Gmail peut mettre en spam. Plus tard : domaine propre (`noreply@…`).

---

## B. Côté Manolya (notre appli)

### 1) `.env`
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=...votre_login_smtp_brevo...
MAIL_PASSWORD=...votre_cle_smtp_brevo...
MAIL_FROM_ADDRESS=amikisawani15@icloud.com
MAIL_FROM_NAME="Manolya Pharma"

QUEUE_CONNECTION=database
# ou redis en prod
```

Puis :
```bash
php artisan config:clear
# en prod : php artisan config:cache
```

### 2) Destinataire = e-mail du user owner
Manolya **n’envoie pas** à une adresse codée en dur.  
Il envoie à chaque user **rôle `owner`** + **`is_active = true`**.

Donc le compte owner doit avoir :
```
email = amikisawani71@gmail.com
```
(Profil / base users — pas seulement `owner@manolya.test`.)

### 3) Worker de file d’attente (obligatoire)
Sans worker, le PDF est généré mais le mail **ne part pas**.

```bash
php artisan queue:work database --tries=3
```
(En prod Coolify/Forge : process permanent.)

### 4) Test métier
1. Ouvrir une session de caisse.
2. Faire une petite vente (optionnel).
3. Clôturer la session.
4. Vérifier :
   - `php artisan queue:failed` (vide = bon signe)
   - PDF dans `storage/app/private/reports/{tenant_id}/cash-sessions/`
   - Boîte **Gmail** (+ dossier Spam) de `amikisawani71@gmail.com`
   - Logs Brevo (Transactional / SMTP)

---

## C. Si ça ne marche pas

| Symptôme | Cause | Action |
|----------|--------|--------|
| Rien dans Gmail | Worker arrêté | Lancer `queue:work` |
| Job failed | SMTP / FROM non vérifié | Vérifier sender iCloud dans Brevo + `.env` |
| Mail en spam | iCloud sans DKIM domaine | Spam Gmail, ou migrer vers domaine |
| PDF généré, pas de mail | Owner mauvais e-mail | Mettre owner = `amikisawani71@gmail.com` |
| `Sender not allowed` | FROM ≠ sender Brevo | `MAIL_FROM_ADDRESS` = iCloud vérifié |

---

## D. Ensuite (amélioration)

- [ ] Domaine officine + SPF/DKIM (remplacer iCloud en FROM)
- [ ] Envoyer aussi au closer / pharmacien
- [ ] E-mail facture de vente PDF au client
- [ ] Alerte UI si échec d’envoi
