# Déploiement Render (gratuit / proche de Vercel)

Choix retenu : **Render** (web Docker gratuit) + **Neon** (Postgres gratuit).  
C’est le duo le plus proche de l’expérience Vercel pour un Laravel.

> Le service web free s’endort après ~15 min d’inactivité (1ʳᵉ requête lente au réveil).

## 1. Base Postgres gratuite (Neon)

1. Créer un compte sur [https://console.neon.tech](https://console.neon.tech)
2. New Project → région proche → créer la DB
3. Copier les infos de connexion :
   - Host, Database, User, Password, Port `5432`
4. Dans Neon, activer éventuellement le pooling ; pour Laravel simple, connexion directe OK.

## 2. Clé Laravel

En local :

```bash
php artisan key:generate --show
```

Garder la ligne `base64:...` pour Render (`APP_KEY`).

## 3. Blueprint Render

1. Compte [https://dashboard.render.com](https://dashboard.render.com) (login GitHub)
2. **New** → **Blueprint** → repo `Amikisawani/manolya-pharma` (branche `main`)
3. Appliquer `render.yaml` → service **manolya-web**
4. Renseigner les variables (Environment) :

| Variable | Valeur |
|----------|--------|
| `APP_KEY` | `base64:...` (étape 2) |
| `APP_URL` | `https://<nom-service>.onrender.com` (après 1er deploy, mettre à jour) |
| `DB_HOST` | host Neon |
| `DB_DATABASE` | nom DB Neon |
| `DB_USERNAME` | user Neon |
| `DB_PASSWORD` | password Neon |
| `MAIL_USERNAME` | login SMTP Brevo |
| `MAIL_PASSWORD` | clé SMTP Brevo |
| `MAIL_FROM_ADDRESS` | `amikisawani15@icloud.com` |

5. Deploy → attendre le build Docker
6. Ouvrir l’URL Render → `/login`

## 4. Seed démo (optionnel)

Shell Render du service :

```bash
php artisan db:seed --force
```

## 5. E-mails / queue

`docker/render-start.sh` lance un mini `queue:work` dans le conteneur web (plan free sans worker séparé).

## Checklist

- [ ] Neon créé
- [ ] `APP_KEY` collé
- [ ] Blueprint Render déployé
- [ ] `MAIL_*` OK
- [ ] Login fonctionne
- [ ] Test clôture caisse → mail
