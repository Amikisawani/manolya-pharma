# Déploiement Manolya Pharma

**Dépôt GitHub :** [https://github.com/Amikisawani/manolya-pharma](https://github.com/Amikisawani/manolya-pharma)

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
   branche `main`, build pack **Dockerfile**.
2. Ajouter services **PostgreSQL 16** + **Redis**.
3. Injecter les variables ci-dessus (`APP_KEY` générée, `APP_URL` = domaine Coolify).
4. Le `Dockerfile` exécute déjà `migrate` + caches au démarrage.
5. Processus supplémentaires (recommandé) :
   - Worker : `php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600`
   - Scheduler : `php artisan schedule:work` (ou cron `* * * * * php artisan schedule:run`)
6. Premier login : créer un owner (ne pas laisser le mot de passe démo en prod).  
   Seed démo uniquement si environnement de test : `php artisan db:seed --force`.

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
