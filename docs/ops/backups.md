# Sauvegardes PostgreSQL

Objectif pilote : dump quotidien + rétention 14 jours, hors machine d’application si possible.

## Script inclus

Fichier : `scripts/backup-postgres.sh`

Variables attendues :

```bash
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=manolya
export DB_USERNAME=manolya
export PGPASSWORD='...'          # ou .pgpass
export BACKUP_DIR=/var/backups/manolya
export RETENTION_DAYS=14
```

Exécution manuelle :

```bash
chmod +x scripts/backup-postgres.sh
./scripts/backup-postgres.sh
```

## Cron (serveur / Coolify scheduled job)

Chaque nuit à 02:15 Africa/Kinshasa (ajuster le TZ du serveur) :

```cron
15 2 * * * /var/www/html/scripts/backup-postgres.sh >> /var/log/manolya-backup.log 2>&1
```

## Docker Compose

Exemple one-shot :

```bash
docker compose exec -T postgres \
  pg_dump -U manolya -d manolya -Fc \
  > "./backups/manolya-$(date +%Y%m%d-%H%M).dump"
```

Monter un volume dédié `./backups` ou un stockage objet (S3 / R2) via sync.

## Restauration

Dump custom (`-Fc`) :

```bash
pg_restore --clean --if-exists -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" /path/to/manolya-YYYYMMDD.dump
```

Dump SQL plain :

```bash
psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" < /path/to/manolya-YYYYMMDD.sql
```

Toujours tester une restauration sur une base secondaire avant un incident réel.

## Bonnes pratiques

- Chiffrer les dumps au repos si le disque n’est pas chiffré
- Copier hors site (autre VPS / objet) au moins 1× / jour
- Vérifier la taille du dump et l’âge du plus récent chaque lundi
- Ne pas stocker `PGPASSWORD` dans git
