# Déploiement Manolya Pharma

**Dépôt GitHub :** [https://github.com/Amikisawani/manolya-pharma](https://github.com/Amikisawani/manolya-pharma)  
**Branche déployable actuelle :** `release/p1` (à fusionner vers `main` sur GitHub si besoin)

Guide opérationnel pour un pilote (Coolify, Forge, ou VPS Docker).

## Prérequis

- PHP 8.3+, Composer, Node 22+
- PostgreSQL 16
- Redis (cache / files d’attente)
- Reverse proxy HTTPS (Caddy / Traefik / Nginx)

## Variables d’environnement (prod)

Copier `.env.example` puis forcer au minimum :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://manolya.example.com
APP_KEY=base64:...

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=manolya
DB_USERNAME=manolya
DB_PASSWORD=...

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=...

MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@example.com

CURRENCY_DEFAULT=CDF
FX_USD_CDF=2850
FX_EUR_CDF=3100

SCOUT_DRIVER=collection

# Optionnel — voir docs/ops/sms-momo.md
SMS_ENABLED=false
SMS_DRIVER=log
MOMO_DEFAULT_PROVIDER=stub

# Optionnel Phase 4
# SENTRY_LARAVEL_DSN=
# SENTRY_TRACES_SAMPLE_RATE=0.1
```

Ne jamais committer `.env` ni les dumps de backup.

## Coolify (recommandé pilote)

1. Nouvelle ressource → **Public repository**  
   `https://github.com/Amikisawani/manolya-pharma`  
   branche `release/p1` (ou `main` après merge), build pack **Dockerfile**.
2. Ajouter services **PostgreSQL 16** + **Redis**.
3. Injecter les variables ci-dessus (`APP_KEY` générée, `APP_URL` = domaine Coolify).
4. Le `Dockerfile` lance nginx + PHP-FPM + le worker de file d’attente (plus `php artisan serve`). Health-check : `GET /up`.
5. Scheduler (recommandé) : `php artisan schedule:work` (ou cron `* * * * * php artisan schedule:run`). Le worker d’import est déjà dans le conteneur.
6. Premier login : créer un owner (ne pas laisser le mot de passe démo en prod).  
   Seed démo uniquement si environnement de test : `php artisan db:seed --force`.

## Render

Le 502 « Bad Gateway » (Request ID `…-SEA`) signifie que **rien ne répond derrière le proxy** : le process PHP est mort, bloqué, ou n’écoute pas `$PORT`.

Ne pas utiliser `php artisan serve` en prod. L’image Docker lance **nginx + PHP-FPM** (plusieurs workers) et un **queue worker**. L’import Excel est un job (`imports`) : il ne bloque plus le site.

Sur le service Render :

1. Runtime **Docker**, `Dockerfile` à la racine, health check **`/up`**.
2. Laisser Render injecter `PORT` (ne pas forcer le port 80 dans le dashboard si Render envoie 10000).
3. Variables **sans Redis** (un seul web service) :

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-service.onrender.com
APP_KEY=base64:...

DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SCOUT_DRIVER=collection
```

Si `SESSION_DRIVER=redis` alors que Redis n’existe pas, chaque page plante.

4. Après un 502 : **Manual Deploy → Restart** une fois l’image nginx/FPM déployée, puis réessayer l’import.

## Laravel Forge

- Site PHP 8.3, Nginx, PostgreSQL managé ou externe.
- Repo : `Amikisawani/manolya-pharma`
- Deploy script typique :

```bash
cd /home/forge/manolya
git pull origin main
$FORGE_COMPOSER install --no-dev --optimize-autoloader
npm ci && npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan queue:restart
```

- Daemon : `queue:work`
- Scheduler : activé dans Forge

## Docker Compose local / VPS

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force   # démo uniquement
```

Prod : pas de bind-mount du code, monter `storage` + backups, `APP_DEBUG=false`.

## Checklist go-live pilote

- [ ] Repo GitHub synchronisé (`main`)
- [ ] `APP_DEBUG=false`, HTTPS OK
- [ ] Migrations appliquées
- [ ] Compte owner créé (pas le mot de passe démo)
- [ ] Backup PostgreSQL planifié (voir `docs/ops/backups.md`)
- [ ] Worker + scheduler actifs
- [ ] Parcours : ouvrir caisse → vente → ticket → clôture
- [ ] Parcours : commande → réception → inventaire
- [ ] E-mails sortants testés

## Rollback rapide

1. Remettre l’image / commit précédent sur Coolify/Forge.
2. Restaurer le dernier dump PostgreSQL (voir backups).
3. `php artisan config:cache` + redémarrer queue.
